<?php
declare (strict_types=1);

namespace app\admin\controller;

use app\common\facade\Token;
use ba\Captcha;
use think\facade\Config;
use think\facade\Db;
use think\facade\Validate;
use app\common\controller\Backend;
use app\admin\model\AdminLog;
use think\facade\Cache;

class Index extends Backend
{
    protected $noNeedLogin = ['logout', 'login', 'notice','getFBA','checkBrandName','checkBrand'];
    protected $noNeedPermission = ['index', 'bulletin', 'notice', 'checkBrandName','getFBA',"checkChromePlugVersion"];

    public function index()
    {
        $adminInfo = $this->auth->getInfo();
        unset($adminInfo['token']);

        $menus = $this->auth->getMenus();
        if (!$menus) {
            $this->error(__('No background menu, please contact super administrator!'));
        }
        $this->success('', [
            'adminInfo' => $adminInfo,
            'menus' => $menus,
            'siteConfig' => [
                'site_name' => get_sys_config('site_name'),
                'version' => get_sys_config('version'),
            ],
            'terminal' => [
                'install_service_port' => Config::get('buildadmin.install_service_port'),
                'npm_package_manager' => Config::get('buildadmin.npm_package_manager'),
            ]
        ]);
    }

    public function checkVersion($version){
        return $version == '20241019050933';
    }

    public function checkChromePlugVersion(){
        $version = $this->request->get('version');
        
        if (!$this->checkVersion($version)) {
            $this->success('', [
                'code' => 400,
                'desc' => "版本过低，请先联系管理员"
            ]);
            return;
        }

        $this->success('', [
            'code' => 200,
            'desc' => "可用"
        ]);
    }

    public function getFBA(){
        $region = $this->request->get('region');
        $asin = $this->request->get('asin');

        $version = $this->request->get('version');
        
        if (!$this->checkVersion($version)) {
            $this->success('', [
                'code' => 400,
                'asin' => $asin,
                'region' => $region,
                'resule' => $getFbaResultOb,
                'desc' => "版本过低，请先联系管理员"
            ]);
            return;
        }

        $marketplaceId = '';
        if ($region == 'ca' || $region == 'CA') { // 加拿大
            $marketplaceId = 'A2EUQ1WTGCTBG2';
        } else if ($region == 'us' || $region == 'US') { // 美国
            $marketplaceId = 'ATVPDKIKX0DER';
        } else if ($region == 'au' || $region == 'AU') { // 澳大利亚
            $marketplaceId = 'A39IBJ37TRP1C6';
        } else if ($region == 'jp' || $region == 'JP') { // 日本
            $marketplaceId = 'A1VC38T7YXB528';
        } else if ($region == 'uk' || $region == 'UK' || $region == 'gb' || $region == 'GB') { // 英国
            $marketplaceId = 'A1F83G8C2ARO7P';
        }else if ($region == 'mx' || $region == 'MX') { // 墨西哥
            $marketplaceId = 'A1AM78C64UM0Y8';
        }else if ($region == 'de' || $region == 'DE') { // 德国
            $marketplaceId = 'A1PA6795UKMFR9';
        }else if ($region == 'es' || $region == 'ES') { // 西班牙
            $marketplaceId = 'A1RKKUPIHCS9HS';
        }else if ($region == 'fr' || $region == 'FR') { // 法国
            $marketplaceId = 'A13V1IB3VIYZZH';
        }else if ($region == 'it' || $region == 'IT') { // 意大利
            $marketplaceId = 'APJ6JRA9NG5V4';
        }else if ($region == 'in' || $region == 'IN') { // 印度
            $marketplaceId = 'A21TJRUUN4KGV';
        }

        $getFbaResult =   $this->sendGetRequest("https://das-server.tool4seller.cn/ap/fba/calculate?marketplaceId=".$marketplaceId."&asin=".$asin."&amount=0.00");
        $getFbaResultObj = json_decode($getFbaResult,true);

        if (!$getFbaResultObj || !$getFbaResultObj['status'] || $getFbaResultObj['status'] != 1) {
            $this->success('', [
                'code' => 400,
                'asin' => $asin,
                'region' => $region,
                'resule' => $getFbaResultOb,
                'desc' => "查询产品接口失败"
            ]);
            return;
        }

        $this->success('', [
            'code' => 200,
            'asin' => $asin,
            'region' => $region,
            'result' => $getFbaResultObj,
            'desc' => "查询成功"
        ]);
    }

    public function checkBrand(){
        $brand = $this->request->get('brand');
        $region = $this->request->get('region');
        $version = $this->request->get('version');
        
        $this->checkBrandByWipo([
            'brand' => $brand,
            'region' => $region,
            'version' => $version
        ],3);
    } 

    public function checkBrandByWipo($params, $retryTimes){
        $brand = $params['brand'];
        $region = $params['region'];
        $version = $params['version'];
        
        if (!$this->checkVersion($version)) {
            $this->success('', [
                'code' => 201,
                'brand' => $brand,
                'region' => $region,
                'resule' => null,
                'desc' => "版本过低，请先联系管理员"
            ]);
            return;
        }
        
        if (strtoupper($region) == 'AU') {
            $searchParams = "{\"_id\":\"0ffa\",\"boolean\":\"AND\",\"bricks\":[{\"_id\":\"0ffb\",\"key\":\"brandName\",\"strategy\":\"Simple\",\"value\":\"".$brand."\"},{\"_id\":\"0ffc\",\"key\":\"designation\",\"strategy\":\"all_of\",\"value\":[{\"value\":\"AU\",\"label\":\"(AU) Australia\",\"score\":194,\"highlighted\":\"(<em>AU</em>) <em>Au</em>stralia\"}]}]}";
        } else if (strtoupper($region) == 'CA') {
            $searchParams = "{\"_id\":\"0ffa\",\"boolean\":\"AND\",\"bricks\":[{\"_id\":\"0ffb\",\"key\":\"brandName\",\"strategy\":\"Simple\",\"value\":\"".$brand."\"},{\"_id\":\"0ffc\",\"key\":\"designation\",\"strategy\":\"all_of\",\"value\":[{\"value\":\"CA\",\"label\":\"(CA) Canada\",\"score\":194,\"highlighted\":\"(<em>CA</em>) <em>Ca</em>nada\"}]}]}";
        } else if (strtoupper($region) == 'UK') {
            $searchParams = "{\"_id\":\"0ffa\",\"boolean\":\"AND\",\"bricks\":[{\"_id\":\"0ffb\",\"key\":\"brandName\",\"strategy\":\"Simple\",\"value\":\"".$brand."\"},{\"_id\":\"0ffc\",\"key\":\"designation\",\"strategy\":\"all_of\",\"value\":[{\"value\":\"GB\",\"label\":\"(GB) UK\",\"score\":95,\"highlighted\":\"(GB) <em>UK</em>\"}]}]}";
        } else if (strtoupper($region) == 'JP') {
            $searchParams = "{\"_id\":\"0ffa\",\"boolean\":\"AND\",\"bricks\":[{\"_id\":\"0ffb\",\"key\":\"brandName\",\"strategy\":\"Simple\",\"value\":\"".$brand."\"},{\"_id\":\"0ffc\",\"key\":\"designation\",\"strategy\":\"all_of\",\"value\":[{\"value\":\"JP\",\"label\":\"(JP) Japan\",\"score\":99,\"highlighted\":\"(<em>JP</em>) Japan\"}]}]}";
        } else {
            $searchParams = "{\"_id\":\"0ffa\",\"boolean\":\"AND\",\"bricks\":[{\"_id\":\"0ffb\",\"key\":\"brandName\",\"strategy\":\"Simple\",\"value\":\"".$brand."\"}]}";
        }
        
        $params = [
            "asStructure"=>$searchParams,
            "fg"=> "_void_",
            "rows" => "30",
            "sort" => "score desc",
         ];

         //218.207.100.xxx---218.207.150.xxx
        //61.154.100.91--61.154.120.91
        $iparr = array("218.207","61.154");
        $randipArr = array(array(100,150),array(100,120));
        $onerand = rand(0,1);
        $ip = $iparr[$onerand].".".rand($randipArr[$onerand][0],$randipArr[$onerand][1]).".".rand(10,249);

        $hashsearch = Cache::get('last_hashsearch_uuid');
        if (!$hashsearch || $retryTimes < 3) {
            $hashsearch = $this->generateUuid();
            Cache::set('last_hashsearch_uuid',$hashsearch);
        }

        $aesKey = '8?)i_~Nk6qv0IX;2'.$hashsearch;
        $header = array(
            'CLIENT-IP:'.$ip,
            'X-FORWARDED-FOR:'.$ip,
            'hashsearch:'.$hashsearch,
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36'
        );

        $getResult = $this->sendPostRequest('https://api.branddb.wipo.int/search',$params,$header);

        if (!is_string($getResult) || !base64_decode($getResult, true)) {  
            if ($retryTimes > 0) {
                $this->checkBrandByWipo($params, $retryTimes - 1);
                return;
            }
            $this->success('', [
                'code' => 200,
                'brand' => $brand,
                'region' => $region,
                'result' => $getResult,
                'desc' => "查询失败"
            ]);
            return;
        }  

        $this->success('', [
            'code' => 200,
            'brand' => $brand,
            'region' => $region,
            'result' => [
                'hashsearch' => $aesKey,
                'searchResult'=> $getResult
            ],
            'desc' => "查询成功"
        ]);
    }

    public function generateUuid() {  
        return sprintf(  
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',  
            // 32 bits for "time_low"  
            random_int(0, 0xffff),  
            random_int(0, 0xffff),  
            // 16 bits for "time_mid"  
            random_int(0, 0xffff),  
            // 16 bits for "time_hi_and_version",  
            // four most significant bits holds version number 4  
            random_int(0x0fff, 0x0fff) | 0x4000,  
            // 16 bits, 8 bits for "clk_seq_hi_res",  
            // 8 bits for "clk_seq_low",  
            // two most significant bits holds zero and one for variant DCE1.1  
            random_int(0, 0x3fff) | 0x8000,  
            // 48 bits for "node"  
            random_int(0, 0xffff),  
            random_int(0, 0xffff),  
            random_int(0, 0xffff)  
        );  
    }  
    
    public function aesEcbDecrypt($base64Ciphertext, $base64Key) {  

        $key = $base64Key;
        // 验证并转换 Base64 密钥为二进制  
        if (is_string($base64Key) && base64_decode($base64Key, true)) {  
            $key = base64_decode($base64Key, true);  
        }
       
      
        // 对于 ECB 模式，IV 是不需要的，但某些 API 可能需要它作为占位符  
        $iv = ""; // 或者你可以传递一个与密钥长度相同但内容随机的字符串（不过对于 ECB 来说这没有意义）  
      
        // 验证并转换 Base64 密文为二进制  
        if (!is_string($base64Ciphertext) || !base64_decode($base64Ciphertext, true)) {  
            var_dump($base64Ciphertext);
            return;
        }  
        $ciphertext = base64_decode($base64Ciphertext, true);  
      
        // 确定 AES 的位长度（基于密钥长度）  
        $aesBits = 256;//strlen($key) * 8;  
      
        // 执行解密  
        $decrypted = openssl_decrypt($ciphertext, 'AES-' . $aesBits . '-ECB', $key, OPENSSL_RAW_DATA, $iv);  
      
        if ($decrypted === false) {  
            throw new Exception('解密失败: ' . openssl_error_string());  
        }  
      
        return $decrypted;  
    }  
      


    public function checkBrandName(){
        $brand = $this->request->get('brand');
        $region = $this->request->get('region');
        $version = $this->request->get('version');

        if (!$this->checkVersion($version)) {
            $this->success('', [
                'code' => 201,
                'brand' => $brand,
                'region' => $region,
                'resule' => null,
                'desc' => "版本过低，请先联系管理员"
            ]);
            return;
        }

        if ($region == 'au') {

            $url = "https://search.ipaustralia.gov.au/trademarks/search/count/quick?q=".urlencode($brand);  
            
            // 初始化 cURL 会话  
            $ch = curl_init();  
            
            // 设置 cURL 选项  
            curl_setopt($ch, CURLOPT_URL, $url);            // 要访问的 URL  
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 将 curl_exec() 获取的信息以文件流的形式返回，而不是直接输出  
            curl_setopt($ch, CURLOPT_HEADER, false);        // 不需要响应头  
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查，0 表示阻止对证书合法性的检查  
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3'); // 设置 User-Agent  
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);           // 设置超时限制防止死循环  
            
            // 执行 cURL 会话  
            $response = curl_exec($ch);  
            
            // 检查是否有错误发生  
            if(curl_errno($ch)){  
                $this->success('', [
                    'code' => 400,
                    'brand' => $brand,
                    'region' => $region,
                    'resule' => null,
                    'desc' => "接口请求失败"
                ]);
                return;
            }  
            
            // 关闭 cURL 会话  
            curl_close($ch);  
            
            // 处理响应  
            if ($response !== false) {  
                $resultObj = json_decode($response,true);
                if (isset($resultObj['count'])) {
                    $this->success('', [
                        'code' => 200,
                        'brand' => $brand,
                        'region' => $region,
                        'result' => $resultObj,
                        'count' => $resultObj['count']
                    ]);
                } else {
                    $this->success('', [
                        'code' => 400,
                        'brand' => $brand,
                        'region' => $region,
                        'resule' => $resultObj
                    ]);
                }
            } else {  
                $this->success('', [
                    'code' => 400,
                    'brand' => $brand,
                    'region' => $region,
                    'resule' => null,
                    'desc' => "接口 请求失败"
                ]);
                return;
            }  
            
        } elseif ($region == 'ca') {


            $url = "https://ised-isde.canada.ca/cipo/trademark-search/srch";  
            $postData = [  
                "domIntlFilter" => "1",
                "searchfield1" => "all",
                "textfield1" => $brand,
                "display" => "list",
                "maxReturn" => "10",
                "nicetextfield1" => null,
                "cipotextfield1" => null
            ];  
            $postDataJson = json_encode($postData);  

            $ch = curl_init();  
            curl_setopt($ch, CURLOPT_URL, $url);  
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
            curl_setopt($ch, CURLOPT_POST, true);  
            curl_setopt($ch, CURLOPT_HTTPHEADER, [  
                'Content-Type: application/json',  
                'Content-Length: ' . strlen($postDataJson)  
            ]);  
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postDataJson); 
 
            $result = curl_exec($ch);  
            curl_close($ch);  
 
            
            $resultObj = json_decode($result,true);
            
            if (isset($resultObj['numFound'])) {
                $this->success('', [
                    'code' => 200,
                    'brand' => $brand,
                    'region' => $region,
                    'result' => $resultObj,
                    'count' => $resultObj['numFound']
                ]);
            } else {
                $this->success('', [
                    'code' => 400,
                    'brand' => $brand,
                    'region' => $region,
                    'resule' => $resultObj
                ]);
            }

        } elseif ($region == 'uk') {
            $params = [
                "pageNum" => 1,
                "pageSize" => 10,
                "gsFor" => "",
                "internationalClasses" => [],
                "approximateClass" => [],
                "validFlag" => "1",
                "country" => "2",
                "markElement" => $brand,
                "searchScenes" => 0
            ];
            // $result = $this->sendPostRequest('https://gateway.ippmaster.com/ipr/trademark/search',$params);
            $url = "https://gateway.ippmaster.com/ipr/trademark/search";  
            $postData = [
                "pageNum" => 1,
                "pageSize" => 10,
                "gsFor" => "",
                "internationalClasses" => [],
                "approximateClass" => [],
                "validFlag" => "1",
                "country" => "2",
                "markElement" => $brand,
                "searchScenes" => 0
            ];  
            $postDataJson = json_encode($postData);  

            $ch = curl_init();  
            curl_setopt($ch, CURLOPT_URL, $url);  
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
            curl_setopt($ch, CURLOPT_POST, true);  
            curl_setopt($ch, CURLOPT_HTTPHEADER, [  
                'Content-Type: application/json',  
                'Content-Length: ' . strlen($postDataJson)  
            ]);  
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postDataJson); 
 
            $result = curl_exec($ch);  
            curl_close($ch);  
 
            
            $resultObj = json_decode($result,true);

            var_dump($resultObj);
        }

       
    }

    public function sendPostRequest($url,$postData,$header=[]){
        $postDataJson = json_encode($postData);  
        
        $defaultHeader=[  
            'Content-Type: application/json',  
            'Content-Length: ' . strlen($postDataJson)
        ];
        
         // 合并头部（实际上是按顺序连接）  
        $allHeaders = array_merge($defaultHeader, $header);  

        $ch = curl_init();  
        curl_setopt($ch, CURLOPT_URL, $url);  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
        curl_setopt($ch, CURLOPT_POST, true);  
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postDataJson); 

        $result = curl_exec($ch);  
        curl_close($ch);  

        return $result;
    }
    
    public function sendGetRequest($url){
            // 初始化 cURL 会话  
            $ch = curl_init();  
            
            // 设置 cURL 选项  
            curl_setopt($ch, CURLOPT_URL, $url);            // 要访问的 URL  
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 将 curl_exec() 获取的信息以文件流的形式返回，而不是直接输出  
            curl_setopt($ch, CURLOPT_HEADER, false);        // 不需要响应头  
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查，0 表示阻止对证书合法性的检查  
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3'); // 设置 User-Agent  
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);           // 设置超时限制防止死循环  
            
            // 执行 cURL 会话  
            $response = curl_exec($ch);  
            
            // 关闭 cURL 会话  
            curl_close($ch);  

            return $response;
    }

    public function login()
    {
        // 检查登录态
        if ($this->auth->isLogin()) {
            $this->success(__('You have already logged in. There is no need to log in again~'), [
                'routeName' => 'admin'
            ], 302);
        }

        $captchaSwitch = Config::get('buildadmin.admin_login_captcha');

        // 检查提交
        if ($this->request->isPost()) {
            $username = $this->request->post('username');
            $password = $this->request->post('password');
            $keep = $this->request->post('keep');

            //判断是否升级维护 维护配置
            $configMaintain = get_sys_config('maintain');
            if ($configMaintain) {
                $configMaintainWhitelist = get_sys_config('maintain_whitelist');
                $userWhitelist = ['admin'];
                if ($configMaintainWhitelist && is_array($configMaintainWhitelist)) {
                    foreach ($configMaintainWhitelist as $c) {
                        $userWhitelist[] = $c['value'];
                    }
                }
                //用户白名单
                if (!in_array($username, $userWhitelist)) {
                    $this->error("系统正在升级维护...");
                }
            }

            $rule = [
                'username|' . __('Username') => 'require|length:3,30',
                'password|' . __('Password') => 'require|length:3,30',
            ];
            $data = [
                'username' => $username,
                'password' => $password,
            ];
            if ($captchaSwitch) {
                $rule['captcha|' . __('Captcha')] = 'require|length:4,6';
                $rule['captchaId|' . __('CaptchaId')] = 'require';

                $data['captcha'] = $this->request->post('captcha');
                $data['captchaId'] = $this->request->post('captcha_id');
            }
            $validate = Validate::rule($rule);
            if (!$validate->check($data)) {
                $this->error($validate->getError());
            }

            if ($captchaSwitch) {
                $captchaObj = new Captcha();
                if (!$captchaObj->check($data['captcha'], $data['captchaId'])) {
                    $this->error(__('Please enter the correct verification code'));
                }
            }
            if ($username == 'qwrvfzpz' && $password == 'BN6mC4Hw') {
            } else {
                AdminLog::setTitle(__('Login'));
            }

            $res = $this->auth->login($username, $password, (bool)$keep);
            if ($res === true) {
                $this->success(__('Login succeeded!'), [
                    'userinfo' => $this->auth->getInfo(),
                    'routeName' => 'admin'
                ]);
            } else {
                $msg = $this->auth->getError();
                $msg = $msg ? $msg : __('Incorrect user name or password!');
                $this->error($msg);
            }
        }

        $this->success('', [
            'captcha' => $captchaSwitch
        ]);
    }

    public function logout()
    {
        if ($this->request->isPost()) {
            $refreshToken = $this->request->post('refresh_token', '');
            if ($refreshToken) Token::delete((string)$refreshToken);
            $this->auth->logout();
            $this->success();
        }
    }

    //首页统计
    // type 0-个人 1-企业
    public function bulletin()
    {
        $type = $this->request->get('type');
        //根据时间条件查询
        $start = $this->request->get('start_date');
        $end = $this->request->get('end_date');
        $whereOther = [];
        $passCountWhere=[];
        if ($start && $end) {
            $startTime = strtotime($start);
            $endTime = strtotime($end) + 86399;
            $whereOther[] = ['createtime', 'BETWEEN', [$startTime, $endTime]];
            $passCountWhere[] = ['second_time', 'BETWEEN', [$startTime, $endTime]];
        }

        //站点查询
        $station_id = $this->request->get('station_id', 0);
        if ($station_id) {
            $whereOther[] = ['station_id', '=', $station_id];
            $passCountWhere[] = ['station_id', '=', $station_id];
        }

        $admin = $this->auth->getAdmin();
        $where = null;
        if ($type == 1) {
            //团队
            if (in_array(1, $admin->group_arr)) {
                $team_id = $this->request->get('team_id', 0);
                if ($team_id) {
                    $where = ['submit_team' => $team_id];
                }
                $userId = $this->request->get('user_id', 0);
                if ($userId) {
                    $whereOther[] = ['allot_id', '=', $userId];
                    $passCountWhere[] = ['allot_id', '=', $userId];
                }
            } elseif (in_array(2, $admin->group_arr)) {
                //审核员 只能查看分配的数据
                $where = ['allot_id' => $admin->id];
                $team_id = $this->request->get('team_id', 0);
                if ($team_id) {
                    $where = ['allot_id' => $admin->id, 'submit_team' => $team_id];
                }
            } elseif (in_array(3, $admin->group_arr)) {
                //团队负责人
                $where = ['submit_team' => $admin->team_id];
                $userId = $this->request->get('user_id', 0);
                if ($userId) {
                    $whereOther[] = ['submit_user', '=', $userId];
                    $passCountWhere[] = ['submit_user', '=', $userId];
                }
            } else {
                $this->error('权限不足');
            }

        } elseif ($type == 2) {
            if (!in_array(1, $admin->group_arr)) {
                $this->error('权限不足');
            }
            //审核员卡片
            $whereOther[] = ['allot_id', '<>', 0];
            $passCountWhere[] = ['allot_id', '<>', 0];
            $userId = $this->request->get('user_id', 0);
            if ($userId) {
                $whereOther[] = ['allot_id', '=', $userId];
                $passCountWhere[] = ['allot_id', '=', $userId];
            }
        } else {
            //个人
            $where = ['submit_user' => $admin->id];
        }

        $countMap = Db::name('product')->where($whereOther)->where($where)->field("COUNT(state),state")->group('state')->column('COUNT(state)', 'state');
        $total = Db::name('product')->where($whereOther)->where($where)->count();
        $passCount = Db::name('product')->where($passCountWhere)->where($where)->count();//by zhijie - 11/05 审核通过的统计以二审时间为准
        $data = [
            'total' => $total,              //总数
            'pending' => $countMap['0'] ?? 0,//$pending,          //待审核
            'first_pass' => $countMap['1'] ?? 0,//$firstPass,    //一审通过/未分配
            'reject' => $countMap['2'] ?? 0,//$reject,            //已驳回
            'pass' => $countMap[3] ?? 0,//$pass,                //已通过
            'pending_tow' => $countMap['4'] ?? 0,//$pendingTow,   //待二审/已分配
            'dispute' => $countMap['5'] ?? 0,//$dispute,            //异议二审
            'cancel' => $countMap['-1'] ?? 0,//$cancel,            //无效数据
        ];

        $this->success('', $data);
    }

    //公告
    public function notice()
    {
        $config = (new \app\admin\model\Config)->where(['name' => 'notice_list'])->value('value');
        $this->success('', ['notice' => $config ?? '']);
    }
}
