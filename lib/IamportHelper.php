<?php

if (!class_exists('IamportHelper')) {

    class IamportHelper
    {

        const STATUS_REFUND = 'status_refund';
        const STATUS_EXCHANGE = 'status_exchange';
        const STATUS_AWAITING_VBANK = 'status_awaiting_vbank';
        const DEFAULT_STATUS_REFUND = '반품요청';
        const DEFAULT_STATUS_EXCHANGE = '교환요청';
        const DEFAULT_STATUS_AWAITING_VBANK = '가상계좌 입금대기 중';

        public static function get_customer_uid($order)
        {
            $prefix = get_option('_iamport_customer_prefix');
            if (empty($prefix)) {
                require_once(ABSPATH . 'wp-includes/class-phpass.php');
                $hasher = new PasswordHash(8, false);
                $prefix = md5($hasher->get_random_bytes(32));

                if (!add_option('_iamport_customer_prefix', $prefix)) throw new Exception(__("정기결제 구매자정보 생성에 실패하였습니다.", 'iamport-for-woocommerce'), 1);
            }

            $user_id = $order->get_user_id(); // wp_cron에서는 get_current_user_id()가 없다.
            if (empty($user_id)) throw new Exception(__("정기결제기능은 로그인된 사용자만 사용하실 수 있습니다.", 'iamport-for-woocommerce'), 1);

            return $prefix . 'c' . $user_id;
        }

        public static function has_excluded_product($order_id, $product_list, $category_list)
        {
            if (empty($product_list) && empty($category_list)) return false;

            $order = new WC_Order($order_id);

            $items = $order->get_items();
            foreach ($items as $it) {
                $product = $it->get_product();

                if ($product instanceof WC_Product) {
                    // subscription in product_list && item is subscription || item in array => included
                    $product_id = $product->get_id();
                    $parent_id = $product->get_parent_id();
                    if (!empty($parent_id)) $product_id = $parent_id;

                    $is_subscription_included = in_array("subscription", $product_list) && self::is_subscription_product($product);
                    $is_product_included = in_array($product_id, $product_list);
                    $is_category_included = IamportHelper::is_product_in_categories($product_id, $category_list);

                    if (!($is_subscription_included || $is_product_included || $is_category_included)) return true;
                }
            }

            return false;
        }

        public static function is_product_in_categories($product_id, $categories)
        {
            $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));

            foreach ($terms as $term_id) {
                if (in_array($term_id, $categories)) return true;
            }

            return false;
        }

        public static function is_subscription_product($product)
        {
            return class_exists('WC_Subscriptions_Product') && WC_Subscriptions_Product::is_subscription($product);
        }

        public static function get_all_products($search = null)
        {
            $condition = array(
                "return" => "ids", //성능이슈 : ids만 받아서 get_posts로 title 조회하는 것이 성능에 좋다.
                "limit" => 50, //안전성을 위해 최대 50개까지만(성능이슈 : SQL이 빨라져도 500개 조회하면 메모리가 부족함)
                "status" => "publish",
                "type" => array('simple', 'variable', 'subscription', 'subscription_variation', 'variable-subscription'),
            );

            if (is_array($search)) $condition += $search;

            //성능이슈 : return : "object" 인경우 개수만큼 Query를 다시 날리는 것으로 확인 됨. wc_get_products에서는 id만 반환한 다음 title 은 get_posts로 구함
            $allProductIDs = wc_get_products($condition);

            $allProducts = get_posts(array(
                "include" => $allProductIDs,
                "post_type" => array('product_variation', 'product')
            ));

            $productNames = array();
            foreach ($allProducts as $p) {
                $productNames[$p->ID] = $p->post_title;
            }

            return $productNames;
        }

        public static function get_all_categories()
        {
            $terms = get_terms('product_cat', array(
                'fields' => 'id=>name',
                'hierarchical' => true,
                'hide_empty' => false,
            ));

            if (!is_array($terms)) { //설정값이 없는 경우 WP_Error가 리턴될 수 있음
                return array();
            }

            return $terms;
        }

        public static function get_tax_free_amount($order)
        {
            // 쿠폰 할인금액은 line_item에 반영된다.
            // 쿠폰 할인금액은 상품 개수별로 1/n 하여 동일하게 할인되며 할인금액의 부가세는 상품의 부가세 속성을 따라감 (면세상품은 면세로 할인, 과세상품은 과세로 할인)

            $product_n_shipping = $order->get_items(array("line_item", "shipping", "fee")); //array of WC_Order_Item_Product, WC_Order_Item_Shipping, WC_Order_Item_Fee. get_total(), get_total_tax()가 존재함

            $tax_free = 0;
            foreach ($product_n_shipping as $item) {
                if ($item->get_total_tax() == 0) { //면세상품 인 경우 합산
                    $tax_free += $item->get_total();
                }
            }

            return $tax_free;
        }

        public static function get_notice_url()
        {
            //legacy 유저 : IamportSettingTab 에서 woocommerce_iamport_notice_url 를 설정하는 기능은 2.1.3에 제거됨. 기존에 설정해놓은 사용자를 고려해, 이미 설정된 값이 있으면 우선 반환
            $notice_url = get_option("woocommerce_iamport_notice_url", null);
            if (filter_var($notice_url, FILTER_VALIDATE_URL)) return $notice_url;

            //[2.1.11] 워드프레스 home_url 로 기본 Notification 설정
            return add_query_arg( array('wc-api'=>WC_Gateway_Iamport_Vbank::class), site_url());
        }

        public static function display_label($type)
        {
            switch ($type) {
                case self::STATUS_REFUND :
                    $label = get_option("woocommerce_iamport_refund_status_label", null);
                    return $label ? $label : self::DEFAULT_STATUS_REFUND;

                case self::STATUS_EXCHANGE :
                    $label = get_option("woocommerce_iamport_exchange_status_label", null);
                    return $label ? $label : self::DEFAULT_STATUS_EXCHANGE;

                case self::STATUS_AWAITING_VBANK :
                    $label = get_option("woocommerce_iamport_awaiting_vbank_status_label", null);
                    return $label ? $label : self::DEFAULT_STATUS_AWAITING_VBANK;
            }

            return "";
        }

        public static function get_order_name($order)
        {
            $order_name = "#" . $order->get_order_number() . "번 주문";

            $cart_items = $order->get_items(); //문제없음
            $cnt = count($cart_items);

            if (!empty($cart_items)) {
                $index = 0;
                foreach ($cart_items as $item) {
                    if ($index == 0) {
                        $order_name = $item->get_name();
                    } else if ($index > 0) {

                        $order_name .= ' 외 ' . ($cnt - 1);
                        break;
                    }

                    $index++;
                }
            }

            $order_name = apply_filters('iamport_simple_order_name', $order_name, $order);

            return $order_name;
        }

        public static function findProductItem($order, $productId, $optionId=null, $attributes=null)
        {
            $items = $order->get_items();
            foreach ($items as $item) {
                if ($productId == $item->get_product_id()) {
                    if ($optionId) {
                        if ($item->get_variation_id() && $optionId == $item->get_variation_id()) {
                            //attributes 까지 한 번 더 체크
                            if (!empty($attributes)) {
                                $variation = wc_get_product( $optionId );
                                if ($variation instanceof WC_Product_Variation) {
                                    $attrKeys = array_keys($variation->get_attributes());

                                    $matched = true;
                                    foreach ($attrKeys as $attrIndex=>$attrKey) {
                                        $attrValue = sanitize_title($item->get_meta($attrKey));
                                        $attrValue = preg_replace('/[^\pL0-9!\+\-\/=_\|]/u', '', $attrValue); //TODO : iamport-naverpay.php 에 중복코드

                                        $check = isset($attributes[$attrIndex]) && $attributes[$attrIndex] == $attrValue;
                                        $matched = $matched && $check;
                                    }

                                    if ($matched) {
                                        return $item;
                                    }
                                }
                            } else {
                                return $item;
                            }
                        }
                    } else {
                        return $item;
                    }
                }
            }

            return null;
        }

        public static function supportMembershipPlugin()
        {
            return class_exists('WC_Memberships');
        }

        public static function getMaxCardQuota()
        {
            $val = get_option("woocommerce_iamport_card_max_quota", null);
            if ($val && preg_match('/^month(\d+)/', $val, $matches)) {
                return intval($matches[1]);
            }

            return 0; //0이면 제한하지 않음
        }

        public static function isIamportGateway($gateway)
        {
            return $gateway && strpos($gateway->id, "iamport_") === 0;
        }

        public static function htmlSecondaryPaymentMethod($settingsText)
        {
            $manualPgOptions = explode(PHP_EOL, $settingsText);
            $pgOptions = array();

            foreach($manualPgOptions as $line) {
                $option = explode(':', $line, 2);
                if (count($option) == 2) {
                    $pgId = trim($option[0]);
                    $label = trim($option[1]);
                    $pgOptions[$pgId] = $label;
                }
            }

            if (!empty($pgOptions)) {
                ob_start(); ?>
                <select class="iamport_payment_method_secondary">
                    <? foreach($pgOptions as $key=>$label) : ?>
                        <option value="<?=$key?>"><?=$label?></option>
                    <? endforeach; ?>
                </select>
                <?php
                return ob_get_clean();
            }

            return '';
        }

        public static function getCustomStatuses()
        {
            $wc_statuses = wc_get_order_statuses();
            $wc_default_statuses = array(
                'wc-pending',
                'wc-processing',
                'wc-on-hold',
                'wc-completed',
                'wc-cancelled',
                'wc-refunded',
                'wc-failed',
            );

            foreach ($wc_default_statuses as $remover) {
                unset($wc_statuses[$remover]);
            }

            return $wc_statuses;
        }

        /**
         * 결제완료되었을 때 processing, completed 외에 다른 상태값으로 변경되도록 커스터마이즈하고 싶은 가맹점
         * 해당 상태값도 결제완료 상태로 간주하여 check_payment_response, process_refund 처리한다
         * 1개만 지정 가능
         */
        public static function paidCustomStatus($withPrefix=true)
        {
            $status = get_option('woocommerce_iamport_custom_status_as_paid', 'none');
            if ($status && $status != 'none') {
                if ($withPrefix === false) {
                    return 'wc-' === substr($status, 0, 3) ? substr($status, 3) : $status;
                }

                return $status;
            }

            return null;
        }
    }

}
