<?php

namespace App\Http\Proxy;
use App\Events\UserLogin;
use App\Events\UserLogout;
use App\Models\LogLogin;
use App\Models\ThreeLogin;
use Illuminate\Support\Facades\Auth;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/10/25 0025
 * Time: 23:02
 */
class TokenProxy
{
    protected $http;

    /**
     * TokenProxy constructor.
     * @param $http
     */
    public function __construct(\GuzzleHttp\Client $http)
    {
        $this->http = $http;
    }

    public function login($email, $password)
    {
        /*
        指定表来校验用户名和密码.该过程就是在显示的指定guards,否则默认走user
        譬如:if(Auth::guard('admin')->attempt($request->only(['username','password'])))
        /config/auth.php中配置guards.名字随意,譬如叫admin
        认证表对应的自定义Model要extends Authenticatable

        attempt方法接收键值数组对作为第一个参数，数组中的值被用于从数据表中查找用户，因此，在这个的例子中，用户将会通过email的值获取
        如果用户被找到，经哈希运算后存储在数据中的密码将会和传递过来的经哈希运算处理的密码值进行比较
        如果两个经哈希运算的密码相匹配那么一个认证session将会为这个用户开启
        如果认证成功的话attempt方法将会返回true，否则返回false

        先验证用户名和密码,验证成功后为用户生成token
        */
        if (auth()->attempt(['email'=> $email, 'password'=> $password])){
            event(new UserLogin());
            return $this->proxy('password', [
                'username' => $email,
                'password' => $password,
                'scope' => '',
            ]);
        }
        return response()->json([
            'status' => 'login error',
            'status_code' => 421,
            'message' => 'Credentials not match'
        ],421);
    }
    public function loginWithThree($email, $password, $id, $provider)
    {
        if (auth()->attempt(['email'=> $email, 'password'=> $password])){
            $user_id = Auth::user()->id;
            event(new UserLogin());
            ThreeLogin::firstOrCreate(['platform_id'=>$id, 'provider'=>$provider, 'user_id' => $user_id]);
            return $this->proxy('password', [
                'username' => $email,
                'password' => $password,
                'scope' => '',
            ]);
        }
        return response()->json([
            'status' => 'login error',
            'status_code' => 421,
            'message' => 'Credentials not match'
        ],421);
    }

    public function proxy($grantType, array $data = [])
    {
        $data     = array_merge($data, ['client_id'     => env('PASSPORT_CLIENT_ID'),
                                        'client_secret' => env('PASSPORT_CLIENT_SECRET'),
                                        'grant_type'    => $grantType
        ]);
        $website = $_SERVER['HTTP_HOST'];
        $response = $this->http->post('http://' . $website . '/oauth/token', ['form_params' => $data
        ]);
        $token = json_decode((string)$response->getBody(), true);//解码转换为PHP变量
        return response()->json(['token'      => $token['access_token'],
                                 'expires_in' => $token['expires_in'],
                                 'status' => 'success',
                                 'status_code' => 200
        ])->cookie('refreshToken', $token['refresh_token'], 14400, null, null, false, true);
    }

    public function logout()
    {
        $user = auth()->guard('api')->user();
        $accessToken = $user->token();
        app('db')->table('oauth_refresh_tokens')
                         ->where('access_token_id', $accessToken->id)
                         ->update([
                             'revoked' => true
                         ]);
        app('cookie')->forget('refreshToken');
        $accessToken->revoke();
 //       $log = new LogLogin();
//        $log->saveLogoutLog($user);
       event(new UserLogout($user));
//        event(new UserLogout($user));
        return response()->json([
            'status' => 'success',
            'status_code' => 200,
            'message' => 'logout success'
            ]
        ,200);
    }

    public function refresh()
    {
        $refreshToken = request()->cookie('refreshToken');
        return $this->proxy('refresh_token',
            ['refresh_token' => $refreshToken]);
    }
}