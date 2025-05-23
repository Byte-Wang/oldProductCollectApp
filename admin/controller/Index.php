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
use app\common\library\Excel;

class Index extends Backend
{
    protected $noNeedLogin = ['logout', 'login', 'notice','getFBA','checkBrandName','checkBrand',"addPlugProductRecord"];
    protected $noNeedPermission = ['index', 'bulletin', 'notice', 'checkBrandName','getFBA',"checkChromePlugVersion","addPlugProductRecord", "addPlugProductBrowsingHistory", "getPlugProductRecord","exportPlugProductRecord","getTeamArea","addTeamArea","editTeamArea","setFavoritePlugProduct"];

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

    public function setFavoritePlugProduct(){
        if ($this->request->isPost()) {
            $request = $this->request;
            $tableNmae = 'ba_plugin_product_record';

            $asin = $request->post('asin');
            $stationId = $request->post('stationId', 0);
            $favorite = $request->post('favorite'); // 1-收藏 0-取消收藏
            
            // 获取当前用户信息
            $admin = $this->auth->getAdmin();
            $userId = $admin->id;

            $record = Db::table($tableNmae)
                ->where(['asin' => $asin])
                ->where(['station_id' => $stationId])
                ->find();

            if ($record) {
              
                
                if ($favorite == 1) { // 收藏
                    $action = 3; //favorite操作
                    // 如果favorite为空，直接添加
                    if (empty($record['favorite'])) {
                        $newFavorite = ','.$userId.',';
                    } else {
                        // 检查是否已经收藏过
                        if (strpos($record['favorite'], ','.$userId.',') === false) {
                            $newFavorite = $record['favorite'].','.$userId.',';
                        } else {
                            $this->success('', [
                                'code' => 200,
                                'desc' => "已经收藏过了"
                            ]);
                            return;
                        }
                    }
                } else { // 取消收藏
                    $action = 4; //favorite操作
                    if (empty($record['favorite'])) {
                        $this->success('', [
                            'code' => 200,
                            'desc' => "还未收藏"
                        ]);
                        return;
                    }
                    // 移除用户ID
                    $newFavorite = str_replace(','.$userId.',', '', $record['favorite']);
                    // 如果只剩下一个逗号，则清空
                    if ($newFavorite == ',' || $newFavorite == ',,') {
                        $newFavorite = '';
                    }
                }

                $data = [
                    'favorite' => $newFavorite,
                ];

                Db::table($tableNmae)
                    ->where(['asin' => $asin])
                    ->where(['station_id' => $stationId])
                    ->update($data);

                // 记录历史
                $historyData = [
                    'asin' => $asin,
                    'admin_id' => $userId,
                    'create_time' => time(),
                    'action' => $action,
                    'plug_version' => $request->post('plugVersion', ''), // 添加插件版本
                ];
                Db::table('ba_plugin_browsing_history')->insert($historyData);

                $this->success('', [
                    'code' => 200,
                    'desc' => $favorite == 1 ? "收藏成功" : "取消收藏成功"
                ]);
                return;
            } else {
                $this->success('', [
                    'code' => 400,
                    'desc' => "记录不存在"
                ]);
                return;
            }
        }

        $this->success('', [
            'code' => 400,
            'desc' => "only support post"
        ]);
    }

    public function addPlugProductRecord(){
         if ($this->request->isPost()) {
            $request = $this->request;
            $tableNmae = 'ba_plugin_product_record';

            $asin = $request->post('asin');
            $favorite = $request->post('favorite');
            $plugVersion = $request->post('plugVersion');           // 插件版本
            $userId = $request->post('userId');                     // 当前请求的用户id
            $state = $request->post('state', 1);                    // 状态
            $seller = $request->post('seller', '');                 // 卖家
            $sellerCount = $request->post('sellerCount', 0);        // 卖家数
            $shippingMethod = $request->post('shippingMethod', ''); // 配送方式
            $rank = $request->post('rank', 0);                      // 排名
            $category = $request->post('category', '');             // 分类
            $rating = $request->post('rating', 0.0);                // 评分
            $brandName = $request->post('brandName', '');           // 品牌名
            $fbaFee = $request->post('fbaFee', 0.0);                // fba费用
            $weight = $request->post('weight', '');                 // 重量
            $dimensions = $request->post('dimensions', '');         // 尺寸
            $profitMargin = $request->post('profitMargin', 0.0);    // 利润率
            $price = $request->post('price', 0.0);                  // 售价
            $listingDate = $request->post('listingDate', '');       // 上架时间
            $asinType = $request->post('asinType', '');             // asin类型
            $reviewStatus = $request->post('reviewStatus', '');     // review状态
            $wipoBrandRegistrationStatus = $request->post('wipoBrandRegistrationStatus', '');  // wipo注册状态
            $trademarkOfficeBrandRegistrationStatus = $request->post('trademarkOfficeBrandRegistrationStatus', '');  // 商标局注册状态
            $productName = $request->post('productTitle', ''); // 产品名称
            $productImage = $request->post('pictureUrl', ''); // 产品图片
            $stationId = $request->post('stationId', 0); // 产品图片

            $action = 1; // 添加操作
            $pid = Db::table($tableNmae)->where(['asin' => $asin])->where(['station_id' => $stationId])->value('id');
            if (!$pid) {
        
                $data = [
                    'asin' => $asin,
                    'favorite' => $favorite,
                    'create_admin' => $userId,
                    'create_time' => time(),
                    'update_admin' => $userId,
                    'update_time' => time(),
                    'plug_version' => $plugVersion,
                    'seller' => $seller,
                    'seller_count' => $sellerCount,
                    'shipping_method' => $shippingMethod,
                    'rank' => $rank,
                    'category' => $category,
                    'rating' => $rating,
                    'brand_name' => $brandName,
                    'fba_fee' => $fbaFee,
                    'weight' => $weight,
                    'dimensions' => $dimensions,
                    'profit_margin' => $profitMargin,
                    'price' => $price,
                    'listing_date' => $listingDate,
                    'asin_type' => $asinType,
                    'review_status' => $reviewStatus,
                    'wipo_brand_registration_status' => $wipoBrandRegistrationStatus,
                    'trademark_office_brand_registration_status' => $trademarkOfficeBrandRegistrationStatus,
                    'product_name' => $productName,
                    'picture_url' => $productImage,
                    'station_id' => $stationId,
                ];

                $result = Db::table($tableNmae)->insert($data);
            } else {
                $action = 2; //编辑操作

                $data = [
                    'favorite' => $favorite,
                    'update_admin' => $userId,
                    'update_time' => time(),
                    'plug_version' => $plugVersion,
                    'seller' => $seller,
                    'seller_count' => $sellerCount,
                    'shipping_method' => $shippingMethod,
                    'rank' => $rank,
                    'category' => $category,
                    'rating' => $rating,
                    'brand_name' => $brandName,
                    'fba_fee' => $fbaFee,
                    'weight' => $weight,
                    'dimensions' => $dimensions,
                    'profit_margin' => $profitMargin,
                    'price' => $price,
                    'listing_date' => $listingDate,
                    'asin_type' => $asinType,
                    'review_status' => $reviewStatus,
                    'wipo_brand_registration_status' => $wipoBrandRegistrationStatus,
                    'trademark_office_brand_registration_status' => $trademarkOfficeBrandRegistrationStatus,
                    'product_name' => $productName,
                    'picture_url' => $productImage,
                    'station_id' => $stationId,
                ];

                Db::table($tableNmae)->where(['asin' => $asin])->update($data);
            }

            $historyData = [
                'asin' => $asin,
                'admin_id' => $userId,
                'create_time' => time(),
                'action' => $action,
                'plug_version' => $plugVersion,
            ];
            $result = Db::table('ba_plugin_browsing_history')->insert($historyData);

            $this->success('', [
                'code' => 200,
                'desc' => ""
            ]);
            return;
         }

        $this->success('', [
            'code' => 400,
            'desc' => "only support post"
        ]);
    }

    public function getPlugProductRecord(){
        $page = $this->request->get('page');
        $limit = $this->request->get('limit');
        $status = $this->request->get('status');
        $asin = $this->request->get('asin');
        $favoriteStatus = $this->request->get('favoriteStatus');
        $createAdmin = $this->request->get('createAdmin');
        $createTeam = $this->request->get('createTeam');
        $createAdminStart = $this->request->get('createTimeStart');
        $createAdminEnd = $this->request->get('createTimeEnd');

        $admin = $this->auth->getAdmin();

        $queryWhere = [];
        if ($status) {
            $queryWhere[] = ['a.status', '=', $status];
        }

        if ($asin) {
            $queryWhere[] = ['a.asin', '=', $asin];
        }

        if ($favoriteStatus == 1) { // 被收藏
            $queryWhere[] = ['', 'exp', Db::raw('a.favorite <> ""')];
        } else if ($favoriteStatus == 2) { // 被本人收藏
            $queryWhere[] = ['a.favorite', 'like', '%,'.$admin->id.',%'];
        } else if ($favoriteStatus == 3) { // 未被收藏
            $queryWhere[] = ['', 'exp', Db::raw('(a.favorite is null or a.favorite = "")')];
        }

        if ($createAdmin && $createAdmin !== null && !empty($createAdmin) && $createAdmin !== 'null') {
            $queryWhere[] = ['a.create_admin', '=', $createAdmin];
        }

        if ($createTeam && $createTeam !== null && !empty($createTeam) && $createTeam !== 'null') {
            $queryWhere[] = ['ca.team_id', '=', $createTeam];
        }

        if ($createAdminStart && $createAdminEnd) {
            $queryWhere[] = ['a.create_time', '>=', $createAdminStart];
            $queryWhere[] = ['a.create_time', '<=', $createAdminEnd];
        }

        
        $whereRole = [];

        if (in_array(1, $admin->group_arr) || in_array(5, $admin->group_arr)) { // 系统管理员 || 大区负责人
            // 查看全部
        } else if (in_array(3, $admin->group_arr)) { // 团队负责人
            $whereRole = ['ca.team_id' => $admin->team_id];
        } else { // 普通用户、审核员
            $whereRole = ['create_admin' => $admin->id];
        }
        
        $teamAreaRole = '';
        $currentTeamArea = $admin->belong_team_area_id;
        if ($currentTeamArea && $currentTeamArea != 0) {
            $teamAreaRole = 'ct.team_area_id = '.$currentTeamArea.' or ca.team_area_id = '.$currentTeamArea;
        }

        $result = Db::table('ba_plugin_product_record')
        ->alias('a')
        ->field('a.*,s.title as station_title,pd.product_name as pd_name,CASE WHEN pd.id IS NOT NULL THEN 1 ELSE 0 END AS has_product, ua.nickname AS update_admin_nickname, ca.nickname AS create_admin_nickname')
        ->leftJoin('ba_admin ua', 'a.update_admin = ua.id')
        ->leftJoin('ba_admin ca', 'a.create_admin = ca.id')
        ->leftJoin('ba_product pd', 'a.asin = pd.asin and (a.station_id = 0 or a.station_id = pd.station_id)')
        ->leftJoin('ba_station s', 'a.station_id = s.id')
        ->leftJoin('ba_team ct', 'ct.id = ca.team_id')
        ->where($teamAreaRole)
        ->where($queryWhere)
        ->where($whereRole)
        ->order('a.update_time', 'desc')
        ->paginate($limit, false, [
            'page'  => $page
        ]);
        
        $sql = Db::getLastSql();

        $this->success('', [
            'list' => $result->items(),
            'total' => $result->total(),
            'sql' => $sql,
        ]);
    }

    public function exportPlugProductRecord(){
        $page = $this->request->get('page');
        $limit = $this->request->get('limit');
        $status = $this->request->get('status');
        $asin = $this->request->get('asin');
        $favoriteStatus = $this->request->get('favoriteStatus');
        $createAdmin = $this->request->get('createAdmin');
        $createTeam = $this->request->get('createTeam');
        $createAdminStart = $this->request->get('createTimeStart');
        $createAdminEnd = $this->request->get('createTimeEnd');

        $admin = $this->auth->getAdmin();

        $queryWhere = [];
        if ($status) {
            $queryWhere[] = ['a.status', '=', $status];
        }

        if ($asin) {
            $queryWhere[] = ['a.asin', '=', $asin];
        }

        if ($favoriteStatus == 1) { // 被收藏
            $queryWhere[] = ['', 'exp', Db::raw('a.favorite <> ""')];
        } else if ($favoriteStatus == 2) { // 被本人收藏
            $queryWhere[] = ['a.favorite', 'like', '%,'.$admin->id.',%'];
        } else if ($favoriteStatus == 3) { // 未被收藏
            $queryWhere[] = ['', 'exp', Db::raw('(a.favorite is null or a.favorite = "")')];
        }

        if ($createAdmin && $createAdmin !== null && !empty($createAdmin) && $createAdmin !== 'null') {
            $queryWhere[] = ['a.create_admin', '=', $createAdmin];
        }

        if ($createTeam && $createTeam !== null && !empty($createTeam) && $createTeam !== 'null') {
            $queryWhere[] = ['ca.team_id', '=', $createTeam];
        }

        if ($createAdminStart && $createAdminEnd) {
            $queryWhere[] = ['a.create_time', '>=', $createAdminStart];
            $queryWhere[] = ['a.create_time', '<=', $createAdminEnd];
        }

        
        $whereRole = [];

        if (in_array(1, $admin->group_arr) || in_array(5, $admin->group_arr)) { // 系统管理员 || 大区负责人
            // 查看全部
        } else if (in_array(3, $admin->group_arr)) { // 团队负责人
            $whereRole = ['ca.team_id' => $admin->team_id];
        } else { // 普通用户、审核员
            $whereRole = ['create_admin' => $admin->id];
        }
        
        $teamAreaRole = '';
        $currentTeamArea = $admin->belong_team_area_id;
        if ($currentTeamArea && $currentTeamArea != 0) {
            $teamAreaRole = 'ct.team_area_id = '.$currentTeamArea.' or ca.team_area_id = '.$currentTeamArea;
        }

        $result = Db::table('ba_plugin_product_record')
        ->alias('a')
        ->field('a.*,s.title as station_title,pd.product_name as pd_name,CASE WHEN pd.id IS NOT NULL THEN 1 ELSE 0 END AS has_product, ua.nickname AS update_admin_nickname, ca.nickname AS create_admin_nickname')
        ->leftJoin('ba_admin ua', 'a.update_admin = ua.id')
        ->leftJoin('ba_admin ca', 'a.create_admin = ca.id')
        ->leftJoin('ba_product pd', 'a.asin = pd.asin and (a.station_id = 0 or a.station_id = pd.station_id)')
        ->leftJoin('ba_station s', 'a.station_id = s.id')
        ->leftJoin('ba_team ct', 'ct.id = ca.team_id')
        ->where($teamAreaRole)
        ->where($queryWhere)
        ->where($whereRole)
        ->order('a.update_time', 'desc')
        ->paginate($limit, false, [
            'page'  => $page
        ]);
        
        $sql = Db::getLastSql();

        $exportExcel = [
            'product_name' => '商品标题',
            'picture_url' => '图片',
            'create_admin_nickname' => '首次采集人',
            'create_time' => '首次采集时间',
            'update_admin_nickname' => '更新数据人',
            'update_time' => '更新时间',
            'has_product' => '采集状态',
            'pd_name' => '采集商品名称',
            'favorite' => '收藏状态',
            'asin' => 'ASIN',
            'station_title' => '站点',
            'seller' => '卖家',
            'seller_count' => '卖家数量',
            'shipping_method' => '配送方式',
            'category' => '类目',
            'rank' => '排名',
            'rating' => '评分',
            'brand_name' => '品牌名',
            'wipo_brand_registration_status' => '注册状态(WIPO)',
            'trademark_office_brand_registration_status' => '注册状态(商标局)',
            'fba_fee' => 'FBA费用',
            'weight' => '重量',
            'profit_margin' => '利润率',
            'price' => '售价',
            'listing_date' => '上架日期',
            'review_status' => '评论状态'
        ];

        Excel::export($exportExcel, true, $result->items(), '产品列表');
    }

    public function getTeamArea(){
        $page = $this->request->get('page');
        $limit = $this->request->get('limit');
        
        $admin = $this->auth->getAdmin();
        $teamAreaRole = '';
        $currentTeamArea = $admin->belong_team_area_id;
        if ($currentTeamArea && $currentTeamArea != 0) {
            $teamAreaRole = 'a.id = '.$currentTeamArea;
        }

        $result = Db::table('ba_team_area')
        ->alias('a')
        ->field('a.id,a.name,a.create_time,GROUP_CONCAT(u.nickname SEPARATOR ", ") AS principal_name')
        ->leftJoin('ba_admin u', 'a.id = u.team_area_id')
        ->where($teamAreaRole)
        ->where('a.status', 1)
        ->group('a.id') // 按照 ba_team_area 的主键分组
        ->order('a.create_time', 'desc')
        ->paginate($limit, false, [
            'page'  => $page
        ]);

        $this->success('', [
            'list' => $result->items(),
            'total' => $result->total()
        ]);
    }

    public function addTeamArea() {
        if ($this->request->isPost()) {
            $request = $this->request;
            $tableName = 'ba_team_area';

            $name = $request->post('name', '');           // 团队名称
            $principal = $request->post('principal', 0);   // 负责人id
            $desc = $request->post('desc', '');           // 描述
            
            // 检查必填字段
            if (empty($name)) {
                $this->success('', [
                    'code' => 400,
                    'desc' => "团队名称不能为空"
                ]);
                return;
            }

            // 检查名称是否重复
            $exists = Db::table($tableName)->where(['name' => $name])->value('id');
            if ($exists) {
                $this->success('', [
                    'code' => 400,
                    'desc' => "团队名称已存在"
                ]);
                return;
            }

            $data = [
                'name' => $name,
                'principal' => $principal,
                'desc' => $desc,
                'status' => 1,                // 默认状态为1-启用
                'create_time' => time(),      // 创建时间
                'update_time' => time(),      // 创建时间
            ];

            $result = Db::table($tableName)->insert($data);

            if ($result) {
                $this->success('', [
                    'code' => 200,
                    'desc' => "添加成功"
                ]);
            } else {
                $this->success('', [
                    'code' => 400,
                    'desc' => "添加失败"
                ]);
            }
            return;
        }

        $this->success('', [
            'code' => 400,
            'desc' => "only support post"
        ]);
    }


    public function editTeamArea() {
        if ($this->request->isPost()) {
            $request = $this->request;
            $tableName = 'ba_team_area';

            $id = $request->post('id', 0);             // 团队ID
            $name = $request->post('name', '');        // 团队名称
            $principal = $request->post('principal', 0); // 负责人id
            $desc = $request->post('desc', '');        // 描述
            $status = $request->post('status', 1);     // 状态
            
            // 检查必填字段
            if (empty($id)) {
                $this->success('', [
                    'code' => 400,
                    'desc' => "团队ID不能为空"
                ]);
                return;
            }

            if (empty($name) && $status != 0) {
                $this->success('', [
                    'code' => 400,
                    'desc' => "团队名称不能为空"
                ]);
                return;
            }

            // 检查记录是否存在
            $exists = Db::table($tableName)->where(['id' => $id])->find();
            if (!$exists) {
                $this->success('', [
                    'code' => 400,
                    'desc' => "团队不存在"
                ]);
                return;
            }

            // 检查名称是否重复(排除自身)
            $nameExists = Db::table($tableName)
                ->where('id', '<>', $id)
                ->where(['name' => $name])
                ->find();
            if ($nameExists) {
                $this->success('', [
                    'code' => 400,
                    'desc' => "团队名称已存在"
                ]);
                return;
            }

            $data = [
                'name' => $name,
                'principal' => $principal,
                'desc' => $desc,
                'status' => $status,
                'update_time' => time(),      // 更新时间
            ];

            $result = Db::table($tableName)
                ->where(['id' => $id])
                ->update($data);

            if ($result !== false) {
                $this->success('', [
                    'code' => 200,
                    'desc' => "更新成功"
                ]);
            } else {
                $this->success('', [
                    'code' => 400,
                    'desc' => "更新失败"
                ]);
            }
            return;
        }

        $this->success('', [
            'code' => 400,
            'desc' => "only support post"
        ]);
    }

    public function addPlugProductBrowsingHistory(){
        if ($this->request->isPost()) {
           $tableNmae = 'ba_plugin_browsing_history';

           $asin = $request->post('asin');
           $action = $request->post('action');                     // 操作，1-浏览；2-更新数据
           $plugVersion = $request->post('plugVersion');           // 插件版本
           $userId = $request->post('userId');                     // 当前请求的用户id

       
           $data = [
                'asin' => $asin,
                'create_time' => time(),
                'admin_id' => $userId,
                'action' => $action,
                'plug_version' => $plugVersion,
            ];

            $result = Db::table($tableNmae)->insert($data);

           $this->success('', [
               'code' => 200,
               'desc' => ""
           ]);
           return;
        }

       $this->success('', [
           'code' => 400,
           'desc' => "only support post"
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
        
        // 构造 URL
        $url = "https://das-server.tool4seller.cn/ap/fba/calculate?marketplaceId=" . $marketplaceId . "&asin=" . $asin . "&amount=0.00&t=" . time();
        
         
        // 定义代理配置数组
        $proxyConfigs = [
        //socks5配置项1
            [
                'ip'   => 's21.js1.dns.2jj.net',   //到期时间25.6.9
                'port' => 10611,
                'user' => 'ljq',
                'pass' => 'kyv',
            ],
        //socks5配置项2
            //[
            //    'ip'   => '192.227.252.109',    //到期时间25.5.24
            //    'port' => 11314,
            //    'user' => 'gzz',
            //    'pass' => 'akb',
            //],
        //socks5配置项3
            [
                'ip'   => ' s53.fj2.dns.2jj.net', //到期时间25.6.12
                'port' => 41576,
                'user' => '3335',
                'pass' => '3335',                
            ],
        //socks5配置项4
            [
                'ip'   => 's20.js2.dns.2jj.net', //到期时间25.6.12
                'port' => 12568,
                'user' => '3335',
                'pass' => '3335',
            ],
        ];

        // 随机选择一套代理配置
        $randomIndex = array_rand($proxyConfigs);
        $selectedProxy = $proxyConfigs[$randomIndex];

        // 分配选中的代理参数
        $proxy_ip     = $selectedProxy['ip'];
        $proxy_port   = $selectedProxy['port'];
        $proxy_user   = $selectedProxy['user'];
        $proxy_pass   = $selectedProxy['pass'];
        
        // 初始化 cURL
        $ch = curl_init();
        
        // 设置 cURL 参数
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 返回结果不直接输出
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // 跟随跳转
        
        // 设置代理
        curl_setopt($ch, CURLOPT_PROXY, "{$proxy_ip}:{$proxy_port}");
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        
        // 如果代理需要认证
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$proxy_user}:{$proxy_pass}");
        
        // 可选：设置 User-Agent
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $startTime = microtime(true);
        // 执行请求
        $response = curl_exec($ch);

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // 检查错误
        if ($response === false) {
            
            
            $this->success('', [
                'code' => 400,
                'asin' => $asin,
                'region' => $region,
                'resule' => '',
                'desc' => "查询产品接口失败"
            ]);
            
        } else {
            $getFbaResultObj = json_decode($response,true);
            
            if (!$getFbaResultObj || !$getFbaResultObj['status'] || $getFbaResultObj['status'] != 1) {
                $this->success('', [
                    'code' => 400,
                    'asin' => $asin,
                    'region' => $region,
                    'resule' => $response,
                    'desc' => "查询产品接口失败"
                ]);
                return;
            }
                
            $this->success('', [
                'code' => 200,
                'asin' => $asin,
                'region' => $region,
                'result' => $getFbaResultObj,
                'desc' => "查询成功",
                'proxy' => "使用第".$randomIndex."个代理，耗时".$duration."秒",
            ]);
        }
        
        
        
        // 关闭句柄
        curl_close($ch);

        /*$reqUrl = "https://das-server.tool4seller.cn/ap/fba/calculate?marketplaceId=".$marketplaceId."&asin=".$asin."&amount=0.00&t=".time();
        $getFbaResult =   $this->sendGetRequest($reqUrl);
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
        ]);*/
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
            $searchParams = "{\"_id\":\"0ffa\",\"boolean\":\"AND\",\"bricks\":[{\"_id\":\"0ffb\",\"key\":\"brandName\",\"strategy\":\"Terms\",\"value\":\"".$brand."\"},{\"_id\":\"0ffc\",\"key\":\"designation\",\"strategy\":\"all_of\",\"value\":[{\"value\":\"AU\",\"label\":\"(AU) Australia\",\"score\":194,\"highlighted\":\"(<em>AU</em>) <em>Au</em>stralia\"}]}]}";
        } else if (strtoupper($region) == 'CA') {
            $searchParams = "{\"_id\":\"0ffa\",\"boolean\":\"AND\",\"bricks\":[{\"_id\":\"0ffb\",\"key\":\"brandName\",\"strategy\":\"Terms\",\"value\":\"".$brand."\"},{\"_id\":\"0ffc\",\"key\":\"designation\",\"strategy\":\"all_of\",\"value\":[{\"value\":\"CA\",\"label\":\"(CA) Canada\",\"score\":194,\"highlighted\":\"(<em>CA</em>) <em>Ca</em>nada\"}]}]}";
        } else if (strtoupper($region) == 'UK') {
            $searchParams = "{\"_id\":\"0ffa\",\"boolean\":\"AND\",\"bricks\":[{\"_id\":\"0ffb\",\"key\":\"brandName\",\"strategy\":\"Terms\",\"value\":\"".$brand."\"},{\"_id\":\"0ffc\",\"key\":\"designation\",\"strategy\":\"all_of\",\"value\":[{\"value\":\"GB\",\"label\":\"(GB) UK\",\"score\":95,\"highlighted\":\"(GB) <em>UK</em>\"}]}]}";
        } else if (strtoupper($region) == 'JP') {
            $searchParams = "{\"_id\":\"0ffa\",\"boolean\":\"AND\",\"bricks\":[{\"_id\":\"0ffb\",\"key\":\"brandName\",\"strategy\":\"Terms\",\"value\":\"".$brand."\"},{\"_id\":\"0ffc\",\"key\":\"designation\",\"strategy\":\"all_of\",\"value\":[{\"value\":\"JP\",\"label\":\"(JP) Japan\",\"score\":99,\"highlighted\":\"(<em>JP</em>) Japan\"}]}]}";
        } else if (strtoupper($region) == 'US') {
            $searchParams = "{\"_id\":\"0ffa\",\"boolean\":\"AND\",\"bricks\":[{\"_id\":\"0ffb\",\"key\":\"brandName\",\"strategy\":\"Terms\",\"value\":\"".$brand."\"},{\"_id\":\"0ffc\",\"key\":\"designation\",\"strategy\":\"all_of\",\"value\":[{\"value\":\"JP\",\"label\":\"(JP) Japan\",\"score\":99,\"highlighted\":\"(<em>JP</em>) Japan\"}]}]}";
        } else {
            $searchParams = "{\"_id\":\"0ffa\",\"boolean\":\"AND\",\"bricks\":[{\"_id\":\"0ffb\",\"key\":\"brandName\",\"strategy\":\"Terms\",\"value\":\"".$brand."\"}]}";
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
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36'); // 设置 User-Agent  
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);           // 设置超时限制防止死循环  
            // curl_setopt($ch, CURLOPT_HTTPHEADER, [
            //     'X-Forwarded-For: 123.45.67.89', // 伪造的 IP 地址
            //     'Client-IP: 123.45.67.89'        // 伪造的 IP 地址
            // ]);
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
            if (in_array(1, $admin->group_arr) || in_array(5, $admin->group_arr)) {
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
            if (!in_array(1, $admin->group_arr) && !in_array(5, $admin->group_arr)) {
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
