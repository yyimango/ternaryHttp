# ternaryRpc
    This is a private warehouse service, feel free to use, problems need to be resolved


## Test code


```php
require_once './vendor/autoload.php';

use Ternary\Http\TernaryHttp;


$url = 'https://tmsgcenterapi.geniuel.com/push_notification';
$params = ['user_list' => ['598'], 'notification' => '测试调用'];

$response = TernaryHttp::asJson()->setConnectTimeout(2)
    ->setRequestTimeout(5)->post($url, $params)->json();
dd($response);

```