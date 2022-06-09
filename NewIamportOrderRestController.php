<?php


class NewIamportOrderRestController extends WP_REST_Controller
{
    const KEY_PRODUCT_ID = 'productId';
    // const KEY_ZONE_ID = 'zoneId';
    // const KEY_PERIOD_ID = 'period';
    // const KEY_MEETING_ROOM_ID = 'meetingRoom';
    // const KEY_BRANCH_ID = 'branches';
    // const KEY_PAYMENT_TYPE_ID = 'paymentType';
    const KEY_CHECK_IN_ID = 'checkInDate';
    // const KEY_SINGLE_BRANCH_ID = 'branch';
    // const KEY_RECEIVING_METHOD_ID = 'receivingMethod';
    // const KEY_RECEIVING_DATE_ID = 'receivingDate';
    // const KEY_RECEIVING_ADDR_ID = 'receivingAddr';
    const KEY_BUYER_DOB = 'buyerDOB';
    const KEY_BUYER_GENDER = 'buyerGender';

    /**
     * OrderRestController constructor.
     */
    public function __construct()
    {
        $this->namespace = 'iamport-for-woocommerce/v2';
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
        // $payMethod = $request['payMethod'];
        // $zone = sanitize_text_field($request['zone']); //TODO : removed
        // $meetingRoom = sanitize_text_field($request['meetingRoom']);
        // $period = sanitize_text_field($request['period']);
        // $paymentType = sanitize_text_field($request['paymentType']);
        // $branches = $request['branches'];
        // $singleBranch = $request['branch']; //20210308 renewal
        // if (empty($branches) && !empty($singleBranch)) {
        //     $branches = [$singleBranch];
        // }
        // $reception = $request['reception'];
        // $receptionDate = self::formatKSTDateTime(sanitize_text_field($request['receptionDate']));
        // $receptionTime = $request['receptionTime'];
        // $receptionPostalAddress = $request['receptionPostalAddress'];

        $payingAmount = 0;
        $discountedAmount = 0;

        // $variationId = self::findVariationId($paymentType, $period, $productId, $zone, $meetingRoom);
        $variationId = self::findVariationId($productId);
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
            "productId": "allspot",
            "checkInDate": "2021-01-12T06:12:22.663Z",
            "buyerName": "가나다라",
            "buyerTel": "01012341234",
            "buyerEmail": "jang@siot.do",
            "buyerDOB": "921010",
            "buyerGender": "F"
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

        // if ($leadOrderId) {
            $order = wc_get_order($leadOrderId);
            $order->set_customer_id($user);
        // } else {
        //     $order = wc_create_order(array(
        //         'status' => 'pending',
        //         'customer_id' => $user,
        //     ));
        // }

        $gatewayId = WC_Gateway_Iamport_Subscription::GATEWAY_ID;
        // if ($paymentType == 'basic') {
        //     if ($payMethod == 'card' || $payMethod == 'kakaopay') {
        //         $gatewayId = WC_Gateway_Iamport_Card::GATEWAY_ID;
        //     } else if ($payMethod == 'trans') {
        //         $gatewayId = WC_Gateway_Iamport_Trans::GATEWAY_ID;
        //     } else if ($payMethod == 'vbank') {
        //         $gatewayId = WC_Gateway_Iamport_Vbank::GATEWAY_ID;
        //     }
        // }

        $orderId = $order->get_id();
        if($order->get_items('products') > 0){
            $order->remove_order_items();
        }
        $order->add_product($product);
        $order->set_payment_method($gatewayId);
        $order->set_billing_first_name($buyerName);
        $order->set_billing_email($buyerEmail);
        $order->set_billing_phone($buyerTel);
        $order->calculate_totals(); //save

        // $branchNames = array();
        // foreach ($branches as $b) {
        //     $branchNames[] = self::getBranchName($b);
        // }

        $order->set_customer_note(
            sprintf("상품명 : %s\n체크인 날짜 : %s",
                self::getProductName($productId),
                // self::getZoneName($zone),
                // self::getPeriodName($period),
                // self::getMeetingRoomName($meetingRoom),
                // self::getPaymentTypeName($paymentType),
                $checkInDate
                // implode(', ', $branchNames),
                // self::receptionMethod($reception),
                // $reception == 'offline' ? $receptionDate . ' ' . $receptionTime : '-',
                // $receptionPostalAddress
            )
        );

        if ($product->get_type() == 'subscription') {
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
            // if ($couponCode) {
            //     $appliedResult = $order->apply_coupon($couponCode);
            //     if (is_wp_error($appliedResult)) {
            //         $response = new WP_REST_Response();
            //         $response->set_status(400);
            //         $response->set_data(array(
            //             'error' => $appliedResult->get_error_message(),
            //         ));

            //         return $response;
            //     }

            //     $discountedAmount = $order->get_total_discount();
            // }

            // $payingAmount = $order->get_total();
        }

        //고객 선택정보 저장
        add_post_meta($orderId, self::KEY_PRODUCT_ID, $productId, true);
        // add_post_meta($orderId, self::KEY_ZONE_ID, $zone, true);
        // add_post_meta($orderId, self::KEY_MEETING_ROOM_ID, $meetingRoom, true);
        // add_post_meta($orderId, self::KEY_PERIOD_ID, $period, true);
        // add_post_meta($orderId, self::KEY_BRANCH_ID, $branches, true);
        // add_post_meta($orderId, self::KEY_PAYMENT_TYPE_ID, $paymentType, true);
        add_post_meta($orderId, self::KEY_CHECK_IN_ID, $checkInDate, true);
        // add_post_meta($orderId, self::KEY_SINGLE_BRANCH_ID, $singleBranch, true);
        // add_post_meta($orderId, self::KEY_RECEIVING_METHOD_ID, $reception, true);
        // add_post_meta($orderId, self::KEY_RECEIVING_DATE_ID, $reception == 'offline' ? $receptionDate . ' ' . $receptionTime : '', true);
        // add_post_meta($orderId, self::KEY_RECEIVING_ADDR_ID, $receptionPostalAddress, true);

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
                // 'payMethod' => get_post_meta($orderId, '_iamport_paymethod', true),
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
            "cardName": "신한카드",
            "pgTid": "qwertyuiopasdfghjklzxxcvzcxvbm"
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
            // 'payMethod' => self::getPayMethodFromGateway($gateway->id),
            'payMethod' => 'card',
            'cardName' => get_post_meta($orderId, '_iamport_customer_card_name', true),
            'productId' => get_post_meta($orderId, self::KEY_PRODUCT_ID, true),
            // 'zone' => get_post_meta($orderId, self::KEY_ZONE_ID, true),
            // 'period' => get_post_meta($orderId, self::KEY_PERIOD_ID, true),
            // 'meetingRoom' => get_post_meta($orderId, self::KEY_MEETING_ROOM_ID, true),
            'checkedInDate' => get_post_meta($orderId, self::KEY_CHECK_IN_ID, true),
            // 'branches' => get_post_meta($orderId, self::KEY_BRANCH_ID, true),
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
                // 'vbankName' => $payment->vbank_name,
                // 'vbankNum' => $payment->vbank_num,
                // 'vbankHolder' => $payment->vbank_holder,
                // 'vbankDate' => (new DateTime("now", new DateTimeZone('Asia/Seoul')))->setTimestamp($payment->vbank_date)->format('Y-m-d H:i:s'),
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
        $buyerDOB = sanitize_text_field($request['buyerDOB']);
        $buyerGender = sanitize_text_field($request['buyerGender']);

        $order = wc_create_order(array(
            'status' => 'pending',
        ));

        $order->set_billing_first_name($buyerName);
        $order->set_billing_email($buyerEmail);
        $order->set_billing_phone($buyerTel);

        $buyerGenderToKo = $buyerGender % 2 == 0 ? '여성' : '남성';
        $order->add_order_note(
            sprintf("[Lead Generation]\n신청자 이름 : %s\n신청자 전화번호 : %s\n신청자 Email : %s\n신청자 생년월일 : %s\n신청자 성별 : %s",
                $buyerName,
                $buyerTel,
                $buyerEmail,
                $buyerDOB,
                $buyerGenderToKo
            )
        );

        $orderId = $order->get_id();
        add_post_meta($orderId, self::KEY_BUYER_DOB, $buyerDOB, true);
        add_post_meta($orderId, self::KEY_BUYER_GENDER, $buyerGender, true);

        $order->save();

        $response = array(
            'orderId' => $order->get_id(),
            'buyerName' => $buyerName,
            'buyerTel' => $buyerTel,
            'buyerEmail' => $buyerEmail,
            'buyerDOB' => $buyerDOB,
            'buyerGender' => $buyerGender
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

    private static function findVariationId($productId)
    {
        switch ($productId) {
            case 'allspot':
                return 3580;
            case 'seocho':
                return 3581;
            case 'hongdae':
                return 3582;
            case 'yeouido':
                return 3725;
            case 'banpo':
                return 3726;
            case 'hapjeong':
                return 3727;
            case 'yeoksam':
                return 6180;
            case 'yeongdeungpo':
                return 9847;
            case 'guro':
                return 9848;
            case 'yongsan':
                return 9849;
            case 'seolleung':
                return 9850;
            default:
                return null;
        }

        return null;
    }

    private static function getProductName($productId)
    {
        switch($productId) {
            case 'allspot':
                return 'ALL SPOT 멤버십';
            case 'seocho':
                return 'ONE SPOT 멤버십 - 서초 FIVE SPOT 전용';
            case 'hongdae':
                return 'ONE SPOT 멤버십 - 홍대 FIVE SPOT 전용';
            case 'yeouido':
                return 'ONE SPOT 멤버십 - 여의도 FIVE SPOT 전용';
            case 'banpo':
                return 'ONE SPOT 멤버십 - 반포 FIVE SPOT 전용';
            case 'hapjeong':
                return 'ONE SPOT 멤버십 - 합정 FIVE SPOT 전용';
            case 'yeoksam':
                return 'ONE SPOT 멤버십 - 역삼 FIVE SPOT 전용';
            case 'yeongdeungpo':
                return 'ONE SPOT 멤버십 - 영등포 FIVE SPOT 전용';
            case 'guro':
                return 'ONE SPOT 멤버십 - 구로 FIVE SPOT 전용';
            case 'yongsan':
                return 'ONE SPOT 멤버십 - 용산 FIVE SPOT 전용';
            case 'seolleung':
                return 'ONE SPOT 멤버십 - 선릉 FIVE SPOT 전용';
            default:
                return '';
            // case 'rounge' :
            // case 'everyday' :
            //     return '에브리데이';

            // case 'weekend' :
            //     return '위켄드';

            // case 'night' :
            //     return '나이트';

            // case 'private' :
            //     return '프라이빗 패스';

            // case 'together' :
            //     return '투게더 패스';
        }

        // return '';
    }

    // private static function getZoneName($zoneId)
    // {
    //     switch($zoneId) {
    //         case 'everydayGangnam' :
    //             return '에브리데이 강남';

    //         case 'everydayGangbook' :
    //             return '에브리데이 강북';

    //         case 'everydayNomad' :
    //             return '에브리데이 노마드';

    //         case 'weekend' :
    //             return '위켄드';

    //         case 'night' :
    //             return '나이트';
    //     }

    //     return '';
    // }

    // private static function getPeriodName($period)
    // {
    //     switch($period) {
    //         case '1month' :
    //             return '1개월';

    //         case '3months' :
    //             return '3개월';

    //         case '6months' :
    //             return '6개월';

    //         case 'subscribe' :
    //             return '자동연장';
    //     }

    //     return '자동연장';
    // }

    // private static function getMeetingRoomName($meetingRoom)
    // {
    //     switch($meetingRoom) {
    //         case '5hours' :
    //             return '5시간';

    //         case 'hours' :
    //             return '3시간';

    //         case '1hour' :
    //             return '1시간';
    //     }

    //     return '선택안함';
    // }

    // private static function getBranchName($branchId)
    // {
    //     switch($branchId) {
    //         case 'gangnam2':
    //             return '강남 2호점';

    //         case 'gangnam3':
    //             return '강남 3호점';

    //         case 'gangnam4':
    //             return '강남 4호점';

    //         case 'gangnam5':
    //             return '강남 5호점';

    //         case 'kyodae':
    //             return '교대점';

    //         case 'samsung2':
    //             return '삼성 2호점';
    //         case 'samsung3':
    //             return '삼성 3호점';
    //         case 'samsung4':
    //             return '삼성 4호점';
    //         case 'seoulforest':
    //             return '서울숲점';
    //         case 'seolleung1':
    //             return '선릉 1호점';
    //         case 'seolleung2':
    //             return '선릉 2호점';
    //         case 'sungsoo':
    //             return '성수점';
    //         case 'citihall':
    //             return '시청점';
    //         case 'shinnonhyeon1':
    //             return '신논현 1호점';
    //         case 'shinnonhyeon2':
    //             return '신논현 2호점';
    //         case 'shinsa':
    //             return '신사점';
    //         case 'yeouido':
    //             return '여의도점';
    //         case 'yeoksam3':
    //             return '역삼 3호점';
    //         case 'euljiro':
    //             return '을지로점';
    //         case 'hongdae':
    //             return '홍대점';
    //     }

    //     return $branchId;
    // }

    // private static function getPaymentTypeName($paymentType)
    // {
    //     switch($paymentType) {
    //         case 'basic' :
    //             return '전액 선결제';

    //         case 'subscribe' :
    //             return '구독형';
    //     }

    //     return '';
    // }

    private static function formatKSTDateTime($utcTimestring)
    {
        $dt = new DateTime($utcTimestring, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Asia/Seoul'));

        return $dt->format('Y-m-d');
    }

    // private static function getPayMethodFromGateway($gatewayId)
    // {
    //     switch($gatewayId) {
    //         case WC_Gateway_Iamport_Subscription::GATEWAY_ID :
    //             return 'card';

    //         case WC_Gateway_Iamport_Card::GATEWAY_ID :
    //             return 'card';

    //         case WC_Gateway_Iamport_Trans::GATEWAY_ID :
    //             return 'trans';

    //         case WC_Gateway_Iamport_Vbank::GATEWAY_ID :
    //             return 'vbank';
    //     }

    //     return '';
    // }

    // private static function receptionMethod($reception)
    // {
    //     switch($reception) {
    //         case 'offline' :
    //             return '내 지점 방문';
    //     }

    //     return '우편 수령';
    // }
}
