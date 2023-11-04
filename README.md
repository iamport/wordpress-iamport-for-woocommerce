![portone-banner-772x250](https://github.com/iamport/wordpress-iamport-for-woocommerce/assets/99194042/ef03d728-d30c-4150-8e40-765b4e762c0b)

> 신용카드/실시간이체/가상계좌/휴대폰소액결제/간편결제 가능. 국내 여러 PG사를 지원.

[워드프레스 플러그인 링크](https://wordpress.org/plugins/iamport-for-woocommerce/) 

포트원(구 아임포트)은 국내 PG서비스들을 표준화하고 있는 결제 서비스입니다.<br>
포트원 하나면 국내 여러 PG사들의 결제 기능을 표준화된 동일한 방식으로 사용할 수 있게 됩니다.


이 플러그인은 포트원 서비스를 우커머스(woocommerce)환경에 맞게 적용한 결제 플러그인입니다.
`신용카드` / `실시간계좌이체` / `가상계좌` / `휴대폰소액결제` / `계좌이체` / `간편결제`를 지원합니다.

`네이버페이` `PAYCO(페이코)` `카카오페이` `KG이니시스` `KCP` `토스페이먼츠` `나이스페이먼츠` `KSNET` `웰컴페이먼츠` `헥토파이낸셜` `KICC` `다날` `모빌리언스(휴대폰소액결제)` PG를 지원하고 있습니다.
또한 `우커머스 정기결제 플러그인`도 지원하고 있습니다.


본 포트원 플러그인은 우커머스 플러그인(https://ko.wordpress.org/plugins/woocommerce/)의 버젼 6.8까지 테스트 되었으며, 8.0 이상의 버젼에서는 작동하지 않기 때문에 PG사 신청 전 사용하시는 우커머스 버젼을 꼭 확인해 주세요!!

포트원 우커머스 플러그인 1.4.2 버전부터는 다국어 지원이 가능합니다. 
1.4.1 버전부터는 Woocommerce Subscription(우커머스 정기결제)기능을 지원합니다. (전달되는 카드정보는 워드프레스 내에 저장되지 않고 폐기되며 암호화되어 전송되며 SSL통신을 적용합니다)

http://www.portone.io 에서 아임포트 서비스에 대한 보다 상세한 내용을 확인하실 수 있습니다.

데모 페이지 : http://demo.movingcart.kr <br>

* 포트원 관리자 페이지( https://admin.iamport.kr ) 에서 관리자 회원가입을 합니다.
* 포트원 플러그인을 다운받아 워드프레스에 설치합니다.
* 포트원 콘솔 > 결제연동 > 내 식별코드에서 "가맹점 식별코드", "REST API키", "REST API secret"을 플러그인 설정에 저장합니다.


## 설치
> 포트원 플러그인 설치, https://admin.portone.io 에서 관리자 회원가입, 시스템설정 정보저장이 필요합니다.

1. 다운받은 iamport.zip파일을 `/wp-content/plugins/` 디렉토리에 복사합니다.
2. unzip iamport.zip으로 압축 파일을 해제하면 iamport폴더가 생성됩니다.
3. 워드프레스 관리자페이지에서 'Plugins'메뉴를 통해 "아임포트" 플러그인을 활성화합니다.

<img width="1691" alt="" src="https://github.com/iamport/wordpress-iamport-for-woocommerce/assets/99194042/89ef1e0d-80db-4103-a973-c52c6c6139c9">

4. https://admin.iamport.kr 에서 관리자 회원가입 후 시스템설정 페이지의 "가맹점 식별코드", "REST API키", "REST API secret"를 확인합니다.
   
![screenshot_2](https://github.com/iamport/wordpress-iamport-for-woocommerce/blob/main/assets/screenshot-2.png)

5. 우커머스(woocommerce) 결제 설정 페이지에서 "가맹점 식별코드", "REST API키", "REST API secret" 정보를 저장합니다.


## Action Hook

> 아임포트 for 우커머스 플러그인이 제공하는 action hook
*   `iamport_order_status_changed` : 아임포트에 의해 우커머스 주문 상태가 변경되었을 때 호출($old\_status, $new\_status, $order) 3개의 파라메터 제공
*   `iamport_order_meta_saved` : 아임포트와의 통신 후 주문에 대한 부가 정보를 저장할 때 호출($order\_id, $meta\_key, $meta\_value) 3개의 파라메터 제공 (meta\_key목록 아래 참조)
    *   \_iamport\_provider : 결제된 PG사코드
    *   \_iamport\_paymethod : 결제수단
    *   \_iamport\_pg\_tid : 결제건에 대한 PG사 승인번호
    *   \_iamport\_receipt\_url : 결제건에 대한 매출전표 URL
    *   \_iamport\_vbank_name : (가상계좌 결제 시)발급된 가상계좌 은행명
    *   \_iamport\_vbank_num : (가상계좌 결제 시)발급된 가상계좌 번호
    *   \_iamport\_vbank_date : (가상계좌 결제 시)발급된 가상계좌의 입금기한(unix timestamp)
*   `iamport_simple_order_name` : 일반 상품 주문시 적용되는 상품명 filter($order\_name, $order) 2개의 파라메터 제공
*   `iamport_recurring_order_name` : 정기결제 상품 주문시 적용되는 상품명 filter($order\_name, $order, $isInitial) 3개의 파라메터 제공

## 변경 내역
2.2.18 버전 이후의 패치는 [Github Releases](https://github.com/iamport/wordpress-iamport-for-woocommerce/releases) 에서 확인해보실 수 있습니다.<br>
과거 변경 내역은 [여기](https://github.com/iamport/wordpress-iamport-for-woocommerce/blob/master/manuals/VERSION.md) 있습니다.

## FAQ
### 서비스 소개
https://www.portone.io
### 관리자 페이지
https://admin.portone.io
### 아임포트 docs
https://developers.portone.io/docs/ko/readme
### 페이스북
https://www.facebook.com/iamportservice
### 고객센터
1670-5176 / cs@portone.io
