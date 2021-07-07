<?php


class IamportOrderRestController extends WP_REST_Controller
{
    const KEY_PRODUCT_ID = 'productId';
    const KEY_ZONE_ID = 'zoneId';
    const KEY_PERIOD_ID = 'period';
    const KEY_MEETING_ROOM_ID = 'meetingRoom';
    const KEY_BRANCH_ID = 'branches';
    const KEY_PAYMENT_TYPE_ID = 'paymentType';
    const KEY_CHECK_IN_ID = 'checkInDate';
    const KEY_SINGLE_BRANCH_ID = 'branch';
    const KEY_RECEIVING_METHOD_ID = 'receivingMethod';
    const KEY_RECEIVING_DATE_ID = 'receivingDate';
    const KEY_RECEIVING_ADDR_ID = 'receivingAddr';

    /**
     * OrderRestController constructor.
     */
    public function __construct()
    {
        $this->namespace = 'iamport-for-woocommerce/v1';
        $this->rest_base = 'order';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/'.$this->rest_base, array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'createOrder'),
                'permission_callback' => array($this, 'createOrderPermissionCallback'),
            ),
        ));

        register_rest_route($this->namespace, '/'.$this->rest_base.'/pay', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'payOrder'),
                'permission_callback' => array($this, 'payOrderPermissionCallback'),
            ),
        ));

        register_rest_route($this->namespace, '/'.$this->rest_base.'/(?P<orderId>[A-Za-z0-9]+)/payment', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'queryPayment'),
                'permission_callback' => array($this, 'queryPaymentPermissionCallback'),
            ),
        ));

        register_rest_route($this->namespace, '/'.$this->rest_base.'/(?P<orderId>[A-Za-z0-9]+)/payment/(?P<impUid>[A-Za-z0-9_]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'queryPayment'),
                'permission_callback' => array($this, 'queryPaymentPermissionCallback'),
            ),
        ));

        register_rest_route($this->namespace, '/'.$this->rest_base.'/lead-generation', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'generateLead'),
                'permission_callback' => array($this, 'generateLeadPermissionCallback'),
            ),
        ));
    }

    public function createOrderPermissionCallback($request)
    {
        return true;
    }

    public function payOrderPermissionCallback($request)
    {
        return true;
    }

    public function queryPaymentPermissionCallback($request)
    {
        return true;
    }

    public function generateLeadPermissionCallback($request)
    {
        return true;
    }

    public function createOrder($request)
    {
        $productId = sanitize_text_field($request['productId']); //TODO : variation ID
        $leadOrderId = sanitize_text_field($request['orderId']);

        $buyerName = sanitize_text_field($request['buyerName']);
        $buyerEmail = sanitize_text_field($request['buyerEmail']);
        $buyerTel = sanitize_text_field($request['buyerTel']);
        $checkInDate = self::formatKSTDateTime(sanitize_text_field($request['checkInDate']));
        $couponCode = $request['couponNumber'];
        $payMethod = $request['payMethod'];
        $zone = sanitize_text_field($request['zone']); //TODO : removed
        $meetingRoom = sanitize_text_field($request['meetingRoom']);
        $period = sanitize_text_field($request['period']);
        $paymentType = sanitize_text_field($request['paymentType']);
        $branches = $request['branches'];
        $singleBranch = $request['branch']; //20210308 renewal
        if (empty($branches) && !empty($singleBranch)) {
            $branches = [$singleBranch];
        }
        $reception = $request['reception'];
        $receptionDate = self::formatKSTDateTime(sanitize_text_field($request['receptionDate']));
        $receptionTime = $request['receptionTime'];
        $receptionPostalAddress = $request['receptionPostalAddress'];

        $payingAmount = 0;
        $discountedAmount = 0;

        $variationId = self::findVariationId($paymentType, $period, $productId, $zone, $meetingRoom);
        if (empty($variationId)) {
            $response = new WP_REST_Response();
            $response->set_status(400);
            $response->set_data(array('error' => '해당되는 상품을 찾을 수 없습니다'));

            return $response;
        }

        $product = wc_get_product($variationId);
        if (empty($product)) {
            $response = new WP_REST_Response();
            $response->set_status(400);
            $response->set_data(array('error' => '해당되는 상품을 찾을 수 없습니다'));

            return $response;
        }

        /**
         {
            "productId": "rounge",
            "checkInDate": "2021-01-12T06:12:22.663Z",
            "buyerName": "가나다라",
            "buyerTel": "01012341234",
            "buyerEmail": "jang@siot.do",
            "zone": "everydayGangbook",
            "meetingRoom": "none",
            "period": "1month"
        }
         */

        if (empty($buyerEmail)) {
            $response = new WP_REST_Response();
            $response->set_status(400);
            $response->set_data(array(
                'error' => '이메일주소는 필수입니다.',
            ));

            return $response;
        }

        //customer_uid 가 unique 하기 위해서 항상 새로운 User가 생성되어야 한다.
        $user = wp_insert_user(array(
            'user_login' => self::getAnonymousUserName($buyerEmail),
            'user_pass' => self::getAnonymousPassword(),
            'nickname' => $buyerName,
            'display_name' => $buyerName,
        )); //중복검사를 피하기 위해 일부러 Email 을 넘기지 않음

        if (is_wp_error($user)) {
            $response = new WP_REST_Response();
            $response->set_status(400);
            $response->set_data(array(
                'error' => $user->get_error_message(),
            ));

            return $response;
        }

        if ($leadOrderId) {
            $order = wc_get_order($leadOrderId);
            $order->set_customer_id($user);
        } else {
            $order = wc_create_order(array(
                'status' => 'pending',
                'customer_id' => $user,
            ));
        }

        $gatewayId = WC_Gateway_Iamport_Subscription::GATEWAY_ID;
        if ($paymentType == 'basic') {
            if ($payMethod == 'card' || $payMethod == 'kakaopay') {
                $gatewayId = WC_Gateway_Iamport_Card::GATEWAY_ID;
            } else if ($payMethod == 'trans') {
                $gatewayId = WC_Gateway_Iamport_Trans::GATEWAY_ID;
            } else if ($payMethod == 'vbank') {
                $gatewayId = WC_Gateway_Iamport_Vbank::GATEWAY_ID;
            }
        }

        $orderId = $order->get_id();
        $order->add_product($product);
        $order->set_payment_method($gatewayId);
        $order->set_billing_first_name($buyerName);
        $order->set_billing_email($buyerEmail);
        $order->set_billing_phone($buyerTel);
        $order->calculate_totals(); //save

        $branchNames = array();
        foreach ($branches as $b) {
            $branchNames[] = self::getBranchName($b);
        }

        $order->set_customer_note(
            sprintf("상품명 : %s\n존 : %s\n이용기간 : %s\n회의실포함 : %s\n결제유형 : %s\n체크인 날짜 : %s\n선택지점 : %s\n출입카드 수령방법 : %s\n수령일시 : %s\n수령우편주소 : %s",
                self::getProductName($productId),
                self::getZoneName($zone),
                self::getPeriodName($period),
                self::getMeetingRoomName($meetingRoom),
                self::getPaymentTypeName($paymentType),
                $checkInDate,
                implode(', ', $branchNames),
                self::receptionMethod($reception),
                $reception == 'offline' ? $receptionDate . ' ' . $receptionTime : '-',
                $receptionPostalAddress
            )
        );

        if ($product->get_type() == 'subscription_variation') {
            $subscription = wcs_create_subscription(array(
                'order_id' => $orderId,
                'status' => 'pending', // Status should be initially set to pending to match how normal checkout process goes
                'billing_period' => WC_Subscriptions_Product::get_period( $product ),
                'billing_interval' => WC_Subscriptions_Product::get_interval( $product )
            ));

            // Modeled after WC_Subscriptions_Cart::calculate_subscription_totals()
            $firstBillingDate = $checkInDate . ' 00:00:00'; //UTC이므로 한국시각으로는 9시
            // Add product to subscription
            $subscription->add_product( $product, 1 );
            $subscription->set_payment_method(WC_Gateway_Iamport_Subscription::GATEWAY_ID);

            $dates = array(
                'trial_end'    => $firstBillingDate,
                'next_payment' => $firstBillingDate,
                'end'          => WC_Subscriptions_Product::get_expiration_date( $product, $firstBillingDate ),
            );

            $subscription = wcs_copy_order_address($order, $subscription);

            $subscription->update_dates( $dates );

            wcs_copy_order_meta($order, $subscription, 'subscription');

            //coupon code
            if ($couponCode) {
                $appliedResult = $subscription->apply_coupon($couponCode);
                if (is_wp_error($appliedResult)) {
                    $response = new WP_REST_Response();
                    $response->set_status(400);
                    $response->set_data(array(
                        'error' => $appliedResult->get_error_message(),
                    ));

                    return $response;
                }

                $discountedAmount = $subscription->get_total_discount();
            }

            $subscription->calculate_totals();
            $payingAmount = $subscription->get_total();

            $order->set_total(0); //빌링등록만 진행되므로 total = 0
            $order->save();
        } else {
            //coupon code
            if ($couponCode) {
                $appliedResult = $order->apply_coupon($couponCode);
                if (is_wp_error($appliedResult)) {
                    $response = new WP_REST_Response();
                    $response->set_status(400);
                    $response->set_data(array(
                        'error' => $appliedResult->get_error_message(),
                    ));

                    return $response;
                }

                $discountedAmount = $order->get_total_discount();
            }

            $payingAmount = $order->get_total();
        }

        //고객 선택정보 저장
        add_post_meta($orderId, self::KEY_PRODUCT_ID, $productId, true);
        add_post_meta($orderId, self::KEY_ZONE_ID, $zone, true);
        add_post_meta($orderId, self::KEY_MEETING_ROOM_ID, $meetingRoom, true);
        add_post_meta($orderId, self::KEY_PERIOD_ID, $period, true);
        add_post_meta($orderId, self::KEY_BRANCH_ID, $branches, true);
        add_post_meta($orderId, self::KEY_PAYMENT_TYPE_ID, $paymentType, true);
        add_post_meta($orderId, self::KEY_CHECK_IN_ID, $checkInDate, true);
        add_post_meta($orderId, self::KEY_SINGLE_BRANCH_ID, $singleBranch, true);
        add_post_meta($orderId, self::KEY_RECEIVING_METHOD_ID, $reception, true);
        add_post_meta($orderId, self::KEY_RECEIVING_DATE_ID, $reception == 'offline' ? $receptionDate . ' ' . $receptionTime : '', true);
        add_post_meta($orderId, self::KEY_RECEIVING_ADDR_ID, $receptionPostalAddress, true);

        $response = array(
            'orderId' => $orderId,
            'merchantUid' => $order->get_order_key(),
            'status' => $order->get_status(),
            'amount' => $payingAmount,
            'discountedAmount' => $discountedAmount,
        );

        return rest_ensure_response($response);
    }

    public function payOrder($request)
    {
        $orderId = sanitize_key($request['orderId']);
        $cardNumber = preg_replace('/[^0-9]/', '', sanitize_text_field($request['cardNumber']));
        $expiry = preg_replace('/[^0-9]/', '', sanitize_text_field($request['expiry']));
        $birth = preg_replace('/[^0-9]/', '', sanitize_text_field($request['birth']));
        $pwd2digit = preg_replace('/[^0-9]/', '', sanitize_text_field($request['pwd2digit']));

        $_POST['iamport_subscription-card-number'] = $cardNumber;
        $_POST['iamport_subscription-card-expiry'] = substr($expiry, 4, 2) . substr($expiry, 2, 2);
        $_POST['iamport_subscription-card-birth'] = $birth;
        $_POST['iamport_subscription-card-pwd'] = $pwd2digit;

        $subscriptions = wcs_get_subscriptions_for_order($orderId);

        try {
            $gateway = wc_get_payment_gateway_by_order($orderId);
//            $gateway = new WC_Gateway_Iamport_Subscription();
            $paymentResult = $gateway->process_payment($orderId);

            if ($paymentResult['result'] != 'success') {
                throw new Exception('결제승인에 실패하였습니다.');
            }

            foreach ($subscriptions as $subscription) {
                $subscription->update_status( 'active', '정기결제 등록시작', true );
            }

            $order = wc_get_order($orderId);

            $response = array(
                'amount' => $order->get_total(),
                'discountedAmount' => $order->get_total_discount(),
                'payMethod' => get_post_meta($orderId, '_iamport_paymethod', true),
                'pgTid' => get_post_meta($orderId, '_iamport_pg_tid', true),
                'impUid' => $order->get_transaction_id(),
            );

            return rest_ensure_response($response);
        } catch (Exception $e) {
            //clear subscription for failed payment
            foreach ($subscriptions as $subscription) {
                $subscription->update_status( 'pending', '정기결제 실패', true );
            }


            $response = new WP_REST_Response();
            $response->set_status(500);
            $response->set_data(array('error' => $e->getMessage()));

            return $response;
        }

        /**
         {
            "cardNumber": "9540490000000589",
            "expiry": "202506",
            "pwd2digit": "08",
            "birth": "850408",
            "orderId": "order_uid_1234567890"
        }
         */

        /**
        {
            "amount": "395000",
            "payMethod": "card",
            "cardName": "신한카드",
            "pgTid": "qwertyuiopasdfghjklzxxcvzcxvbm",
            "vbankName": "SC제일은행",
            "vbankNumber": "3220240353",
            "vbankHolder": "최소리",
            "vbankDue": "2020-10-10 10:10:10"
        }
         */

        return rest_ensure_response($response);
    }

    public function queryPayment($request)
    {
        require_once(dirname(__FILE__).'/lib/iamport.php');

        $impUid = $request['impUid']; //register billing 의 경우 empty
        $orderId = $request['orderId'];

        $order = wc_get_order($orderId);
        $gateway = wc_get_payment_gateway_by_order($orderId);
        if (empty($order)) {
            $response = new WP_REST_Response();
            $response->set_status(400);
            $response->set_data(array(
                'error' => '존재하지 않는 주문번호입니다.',
            ));

            return $response;
        }

        $payingAmount = 0;
        $discountedAmount = 0;
        foreach ( wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'parent' ) ) as $subscription ) {
            $payingAmount += $subscription->get_total();
            $discountedAmount += $subscription->get_total_discount();
        }

        $response = array(
            'amount' => $payingAmount,
            'discountedAmount' => $discountedAmount,
            'status' => $order->get_status(),
            'payMethod' => self::getPayMethodFromGateway($gateway->id),
            'cardName' => get_post_meta($orderId, '_iamport_customer_card_name', true),
            'productId' => get_post_meta($orderId, self::KEY_PRODUCT_ID, true),
            'zone' => get_post_meta($orderId, self::KEY_ZONE_ID, true),
            'period' => get_post_meta($orderId, self::KEY_PERIOD_ID, true),
            'meetingRoom' => get_post_meta($orderId, self::KEY_MEETING_ROOM_ID, true),
            'checkedInDate' => get_post_meta($orderId, self::KEY_CHECK_IN_ID, true),
            'branches' => get_post_meta($orderId, self::KEY_BRANCH_ID, true),
            'paidAt' => get_post_meta($orderId, self::KEY_CHECK_IN_ID, true). ' 09:00:00',
        );

        if (!empty($impUid)) {
            $client = new WooIamport($gateway->imp_rest_key, $gateway->imp_rest_secret);
            $apiResponse = $client->findByImpUID($impUid);

            if (!$apiResponse->success) {
                $response = new WP_REST_Response();
                $response->set_status(500);
                $response->set_data(array(
                    'error' => $apiResponse->error['message'],
                ));

                return $response;
            }

            $payment = $apiResponse->data;

            if ($payment->status == 'paid' && $order->get_total() == $payment->amount) {
                if ( !in_array($order->get_status(), wc_get_is_paid_statuses()) ) {
                    $order->payment_complete($impUid);
                }
            }

            $response = array_merge($response, array(
                'amount' => $payment->amount,
                'impUid' => $impUid,
                'pgTid' => $payment->pg_tid,
                'vbankName' => $payment->vbank_name,
                'vbankNum' => $payment->vbank_num,
                'vbankHolder' => $payment->vbank_holder,
                'vbankDate' => (new DateTime("now", new DateTimeZone('Asia/Seoul')))->setTimestamp($payment->vbank_date)->format('Y-m-d H:i:s'),
                'paidAt' => $payment->paid_at ? (new DateTime("now", new DateTimeZone('Asia/Seoul')))->setTimestamp($payment->paid_at)->format('Y-m-d H:i:s') : null,
                'errorCode' => null,
                'errorMsg' => $payment->fail_reason,
            ));
        }

        return rest_ensure_response($response);
    }

    public function generateLead($request)
    {
        $buyerName = sanitize_text_field($request['buyerName']);
        $buyerTel = sanitize_text_field($request['buyerTel']);
        $buyerEmail = sanitize_text_field($request['buyerEmail']);

        $order = wc_create_order(array(
            'status' => 'pending',
        ));

        $order->set_billing_first_name($buyerName);
        $order->set_billing_email($buyerEmail);
        $order->set_billing_phone($buyerTel);

        $order->add_order_note(
            sprintf("[Lead Generation]\n신청자 이름 : %s\n신청자 전화번호 : %s\n신청자 Email : %s",
                $buyerName,
                $buyerTel,
                $buyerEmail
            )
        );

        $order->save();

        $response = array(
            'orderId' => $order->get_id(),
            'buyerName' => $buyerName,
            'buyerTel' => $buyerTel,
            'buyerEmail' => $buyerEmail
        );

        return rest_ensure_response($response);
    }

    private static function getAnonymousUserName($email)
    {
        $randomSalt = microtime(true);
        return wp_slash( md5($randomSalt . $email) );
    }

    private static function getAnonymousPassword()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			// 32 bits for "time_low"
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			// 16 bits for "time_mid"
			mt_rand(0, 0xffff),
			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			mt_rand(0, 0x0fff) | 0x4000,
			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			mt_rand(0, 0x3fff) | 0x8000,
			// 48 bits for "node"
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
    }

    private static function findVariationId($paymentType, $period, $productId, $zoneId, $meetingRoom)
    {
        //20210308 renewal
        if ($zoneId == 'v20210308') {
            if ($productId == 'rounge' || $productId == 'everyday') {
                if ($meetingRoom == "5hours") {
                    return 1141;
                } else {
                    return 1140;
                }
            } else if ($productId == 'weekend') {
                if ($meetingRoom == "5hours") {
                    return 62;
                } else {
                    return 53;
                }
            } else if ($productId == 'night') {
                if ($meetingRoom == "5hours") {
                    return 1144;
                } else {
                    return 1143;
                }
            } else if ($productId == 'together') {
                return 91;
            }
        }

        //초기 적용 버전
        if ($productId == "rounge") {
            if ($zoneId == "everydayGangnam") {
                if ($meetingRoom == "1hour") {
                    if ($period == "3months") {
                        return 18;
                    } else if ($period == "6months") {
                        return 19;
                    }  else if ($period == "subscribe") {
                        return 20;
                    } else { //upfront
                        return 95;
                    }
                } else if ($meetingRoom == "3hours") {
                    if ($period == "3months") {
                        return 21;
                    } else if ($period == "6months") {
                        return 22;
                    }  else if ($period == "subscribe") {
                        return 23;
                    } else { //upfront
                        return 96;
                    }
                } else if ($meetingRoom == "5hours") {
                    if ($period == "3months") {
                        if ($paymentType == "basic") {
                            return 151;
                        } else {
                            return 24;
                        }
                    } else if ($period == "6months") {
                        if ($paymentType == "basic") {
                            return 152;
                        } else {
                            return 25;
                        }
                    }  else if ($period == "subscribe") {
                        return 26;
                    } else { //upfront
                        return 97;
                    }
                } else {
                    if ($period == "3months") {
                        if ($paymentType == "basic") {
                            return 145;
                        } else {
                            return 15;
                        }
                    } else if ($period == "6months") {
                        if ($paymentType == "basic") {
                            return 146;
                        } else {
                            return 16;
                        }
                    }  else if ($period == "subscribe") {
                        return 17;
                    } else { //upfront
                        return 94;
                    }
                }
            } else if ($zoneId == "everydayGangbook") {
                if ($meetingRoom == "1hour") {
                    if ($period == "3months") {
                        return 30;
                    } else if ($period == "6months") {
                        return 31;
                    }  else if ($period == "subscribe") {
                        return 32;
                    } else { //upfront
                        return 99;
                    }
                } else if ($meetingRoom == "3hours") {
                    if ($period == "3months") {
                        return 33;
                    } else if ($period == "6months") {
                        return 34;
                    }  else if ($period == "subscribe") {
                        return 35;
                    } else { //upfront
                        return 100;
                    }
                } else if ($meetingRoom == "5hours") {
                    if ($period == "3months") {
                        if ($paymentType == "basic") {
                            return 159;
                        } else {
                            return 36;
                        }
                    } else if ($period == "6months") {
                        if ($paymentType == "basic") {
                            return 160;
                        } else {
                            return 37;
                        }
                    }  else if ($period == "subscribe") {
                        return 38;
                    } else { //upfront
                        return 101;
                    }
                } else {
                    if ($period == "3months") {
                        if ($paymentType == "basic") {
                            return 153;
                        } else {
                            return 27;
                        }
                    } else if ($period == "6months") {
                        if ($paymentType == "basic") {
                            return 154;
                        } else {
                            return 28;
                        }
                    }  else if ($period == "subscribe") {
                        return 29;
                    } else { //upfront
                        return 98;
                    }
                }
            } else if ($zoneId == "everydayNomad") {
                if ($meetingRoom == "1hour") {
                    if ($period == "3months") {
                        return 42;
                    } else if ($period == "6months") {
                        return 43;
                    }  else if ($period == "subscribe") {
                        return 44;
                    } else { //upfront
                        return 103;
                    }
                } else if ($meetingRoom == "3hours") {
                    if ($period == "3months") {
                        return 45;
                    } else if ($period == "6months") {
                        return 46;
                    }  else if ($period == "subscribe") {
                        return 47;
                    } else { //upfront
                        return 104;
                    }
                } else if ($meetingRoom == "5hours") {
                    if ($period == "3months") {
                        if ($paymentType == "basic") {
                            return 167;
                        } else {
                            return 48;
                        }
                    } else if ($period == "6months") {
                        if ($paymentType == "basic") {
                            return 168;
                        } else {
                            return 49;
                        }
                    }  else if ($period == "subscribe") {
                        return 50;
                    } else { //upfront
                        return 105;
                    }
                } else {
                    if ($period == "3months") {
                        if ($paymentType == "basic") {
                            return 161;
                        } else {
                            return 39;
                        }
                    } else if ($period == "6months") {
                        if ($paymentType == "basic") {
                            return 162;
                        } else {
                            return 40;
                        }
                    }  else if ($period == "subscribe") {
                        return 41;
                    } else { //upfront
                        return 102;
                    }
                }
            } else if ($zoneId == "weekend") {
                if ($meetingRoom == "1hour") {
                    if ($period == "3months") {
                        return 54;
                    } else if ($period == "6months") {
                        return 55;
                    }  else if ($period == "subscribe") {
                        return 56;
                    } else { //upfront
                        return 107;
                    }
                } else if ($meetingRoom == "3hours") {
                    if ($period == "3months") {
                        return 57;
                    } else if ($period == "6months") {
                        return 58;
                    }  else if ($period == "subscribe") {
                        return 59;
                    } else { //upfront
                        return 108;
                    }
                } else if ($meetingRoom == "5hours") {
                    if ($period == "3months") {
                        if ($paymentType == "basic") {
                            return 175;
                        } else {
                            return 60;
                        }
                    } else if ($period == "6months") {
                        if ($paymentType == "basic") {
                            return 176;
                        } else {
                            return 61;
                        }
                    }  else if ($period == "subscribe") {
                        return 62;
                    } else { //upfront
                        return 109;
                    }
                } else {
                    if ($period == "3months") {
                        if ($paymentType == "basic") {
                            return 169;
                        } else {
                            return 51;
                        }
                    } else if ($period == "6months") {
                        if ($paymentType == "basic") {
                            return 170;
                        } else {
                            return 52;
                        }
                    }  else if ($period == "subscribe") {
                        return 53;
                    } else { //upfront
                        return 106;
                    }
                }
            } else if ($zoneId == "night") {
                if ($meetingRoom == "1hour") {
                    if ($period == "3months") {
                        return 66;
                    } else if ($period == "6months") {
                        return 67;
                    }  else if ($period == "subscribe") {
                        return 68;
                    } else { //upfront
                        return 111;
                    }
                } else if ($meetingRoom == "3hours") {
                    if ($period == "3months") {
                        return 69;
                    } else if ($period == "6months") {
                        return 70;
                    }  else if ($period == "subscribe") {
                        return 71;
                    } else { //upfront
                        return 112;
                    }
                } else if ($meetingRoom == "5hours") {
                    if ($period == "3months") {
                        if ($paymentType == "basic") {
                            return 183;
                        } else {
                            return 72;
                        }
                    } else if ($period == "6months") {
                        if ($paymentType == "basic") {
                            return 184;
                        } else {
                            return 73;
                        }
                    }  else if ($period == "subscribe") {
                        return 74;
                    } else { //upfront
                        return 113;
                    }
                } else {
                    if ($period == "3months") {
                        if ($paymentType == "basic") {
                            return 177;
                        } else {
                            return 63;
                        }
                    } else if ($period == "6months") {
                        if ($paymentType == "basic") {
                            return 178;
                        } else {
                            return 64;
                        }
                    }  else if ($period == "subscribe") {
                        return 65;
                    } else { //upfront
                        return 110;
                    }
                }
            }
        } else if ($productId == "private") {
            if ($meetingRoom == "1hour") {
                if ($period == "3months") {
                    if ($paymentType == "basic") {
                        return 125;
                    } else {
                        return 79;
                    }
                } else if ($period == "6months") {
                    if ($paymentType == "basic") {
                        return 126;
                    } else {
                        return 80;
                    }
                }  else if ($period == "subscribe") {
                    return 81;
                } else { //upfront
                    return 116;
                }
            } else if ($meetingRoom == "3hours") {
                if ($period == "3months") {
                    if ($paymentType == "basic") {
                        return 127;
                    } else {
                        return 82;
                    }
                } else if ($period == "6months") {
                    if ($paymentType == "basic") {
                        return 128;
                    } else {
                        return 83;
                    }
                }  else if ($period == "subscribe") {
                    return 84;
                } else { //upfront
                    return 117;
                }
            } else if ($meetingRoom == "5hours") {
                if ($period == "3months") {
                    if ($paymentType == "basic") {
                        return 129;
                    } else {
                        return 85;
                    }
                } else if ($period == "6months") {
                    if ($paymentType == "basic") {
                        return 130;
                    } else {
                        return 86;
                    }
                }  else if ($period == "subscribe") {
                    return 87;
                } else { //upfront
                    return 118;
                }
            } else {
                if ($period == "3months") {
                    if ($paymentType == "basic") {
                        return 123;
                    } else {
                        return 76;
                    }
                } else if ($period == "6months") {
                    if ($paymentType == "basic") {
                        return 124;
                    } else {
                        return 77;
                    }
                }  else if ($period == "subscribe") {
                    return 78;
                } else { //upfront
                    return 115;
                }
            }
        } else if ($productId == "together") {
            if ($period == "3months") {
                if ($paymentType == "basic") {
                    return 121;
                } else {
                    return 89;
                }
            } else if ($period == "6months") {
                if ($paymentType == "basic") {
                    return 122;
                } else {
                    return 90;
                }
            } else if ($period == "subscribe") {
                return 91;
            } else { //upfront
                return 120;
            }
        }

        return null;
    }

    private static function getProductName($productId)
    {
        switch($productId) {
            case 'rounge' :
            case 'everyday' :
                return '에브리데이';

            case 'weekend' :
                return '위켄드';

            case 'night' :
                return '나이트';

            case 'private' :
                return '프라이빗 패스';

            case 'together' :
                return '투게더 패스';
        }

        return '';
    }

    private static function getZoneName($zoneId)
    {
        switch($zoneId) {
            case 'everydayGangnam' :
                return '에브리데이 강남';

            case 'everydayGangbook' :
                return '에브리데이 강북';

            case 'everydayNomad' :
                return '에브리데이 노마드';

            case 'weekend' :
                return '위켄드';

            case 'night' :
                return '나이트';
        }

        return '';
    }

    private static function getPeriodName($period)
    {
        switch($period) {
            case '1month' :
                return '1개월';

            case '3months' :
                return '3개월';

            case '6months' :
                return '6개월';

            case 'subscribe' :
                return '자동연장';
        }

        return '자동연장';
    }

    private static function getMeetingRoomName($meetingRoom)
    {
        switch($meetingRoom) {
            case '5hours' :
                return '5시간';

            case 'hours' :
                return '3시간';

            case '1hour' :
                return '1시간';
        }

        return '선택안함';
    }

    private static function getBranchName($branchId)
    {
        switch($branchId) {
            case 'gangnam2':
                return '강남 2호점';

            case 'gangnam3':
                return '강남 3호점';

            case 'gangnam4':
                return '강남 4호점';

            case 'gangnam5':
                return '강남 5호점';

            case 'kyodae':
                return '교대점';

            case 'samsung2':
                return '삼성 2호점';
            case 'samsung3':
                return '삼성 3호점';
            case 'samsung4':
                return '삼성 4호점';
            case 'seoulforest':
                return '서울숲점';
            case 'seolleung1':
                return '선릉 1호점';
            case 'seolleung2':
                return '선릉 2호점';
            case 'sungsoo':
                return '성수점';
            case 'citihall':
                return '시청점';
            case 'shinnonhyeon1':
                return '신논현 1호점';
            case 'shinnonhyeon2':
                return '신논현 2호점';
            case 'shinsa':
                return '신사점';
            case 'yeouido':
                return '여의도점';
            case 'yeoksam3':
                return '역삼 3호점';
            case 'euljiro':
                return '을지로점';
            case 'hongdae':
                return '홍대점';
        }

        return $branchId;
    }

    private static function getPaymentTypeName($paymentType)
    {
        switch($paymentType) {
            case 'basic' :
                return '전액 선결제';

            case 'subscribe' :
                return '구독형';
        }

        return '';
    }

    private static function formatKSTDateTime($utcTimestring)
    {
        $dt = new DateTime($utcTimestring, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Asia/Seoul'));

        return $dt->format('Y-m-d');
    }

    private static function getPayMethodFromGateway($gatewayId)
    {
        switch($gatewayId) {
            case WC_Gateway_Iamport_Subscription::GATEWAY_ID :
                return 'card';

            case WC_Gateway_Iamport_Card::GATEWAY_ID :
                return 'card';

            case WC_Gateway_Iamport_Trans::GATEWAY_ID :
                return 'trans';

            case WC_Gateway_Iamport_Vbank::GATEWAY_ID :
                return 'vbank';
        }

        return '';
    }

    private static function receptionMethod($reception)
    {
        switch($reception) {
            case 'offline' :
                return '내 지점 방문';
        }

        return '우편 수령';
    }
}