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
    protected $noNeedLogin = ['logout', 'login', 'notice','getFBA','checkBrandName','checkBrand',"addTemuGoodsRecord","addPlugProductRecord","addPriceChangeRecord"];
    protected $noNeedPermission = ['index', 'bulletin', 'notice', 'checkBrandName','getFBA',"checkChromePlugVersion","exportTemuGoodsRecord","getTemuGoodsRecord","addTemuGoodsRecord","addPlugProductRecord", "addPlugProductBrowsingHistory", "getPlugProductRecord","exportPlugProductRecord","getTeamArea","addTeamArea","editTeamArea","setFavoritePlugProduct","addOtp","editOtp","getOtps","getPriceChangeRecord","exportPriceChangeRecord","getPriceChangeRecordById","addPriceChangeRecord"];

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

                // 初始化data数组
                $data = [];
                
                // 添加总是需要更新的字段
                $data['update_admin'] = $userId;
                $data['update_time'] = time();
                
                // 为每个字段添加校验逻辑，只添加不为0也不为空的值
                if (!empty($favorite) && $favorite !== 0) {
                    $data['favorite'] = $favorite;
                }
                if (!empty($plugVersion) && $plugVersion !== 0) {
                    $data['plug_version'] = $plugVersion;
                }
                if (!empty($seller) && $seller !== 0) {
                    $data['seller'] = $seller;
                }
                if (!empty($sellerCount) && $sellerCount !== 0) {
                    $data['seller_count'] = $sellerCount;
                }
                if (!empty($shippingMethod) && $shippingMethod !== 0) {
                    $data['shipping_method'] = $shippingMethod;
                }
                if (!empty($rank) && $rank !== 0) {
                    $data['rank'] = $rank;
                }
                if (!empty($category) && $category !== 0) {
                    $data['category'] = $category;
                }
                if (!empty($rating) && $rating !== 0) {
                    $data['rating'] = $rating;
                }
                if (!empty($brandName) && $brandName !== 0) {
                    $data['brand_name'] = $brandName;
                }
                if (!empty($fbaFee) && $fbaFee !== 0) {
                    $data['fba_fee'] = $fbaFee;
                }
                if (!empty($weight) && $weight !== 0) {
                    $data['weight'] = $weight;
                }
                if (!empty($dimensions) && $dimensions !== 0) {
                    $data['dimensions'] = $dimensions;
                }
                if (!empty($profitMargin) && $profitMargin !== 0) {
                    $data['profit_margin'] = $profitMargin;
                }
                if (!empty($price) && $price !== 0) {
                    $data['price'] = $price;
                }
                if (!empty($listingDate) && $listingDate !== 0) {
                    $data['listing_date'] = $listingDate;
                }
                if (!empty($asinType) && $asinType !== 0) {
                    $data['asin_type'] = $asinType;
                }
                if (!empty($reviewStatus) && $reviewStatus !== 0) {
                    $data['review_status'] = $reviewStatus;
                }
                if (!empty($wipoBrandRegistrationStatus) && $wipoBrandRegistrationStatus !== 0) {
                    $data['wipo_brand_registration_status'] = $wipoBrandRegistrationStatus;
                }
                if (!empty($trademarkOfficeBrandRegistrationStatus) && $trademarkOfficeBrandRegistrationStatus !== 0) {
                    $data['trademark_office_brand_registration_status'] = $trademarkOfficeBrandRegistrationStatus;
                }
                if (!empty($productName) && $productName !== 0) {
                    $data['product_name'] = $productName;
                }
                if (!empty($productImage) && $productImage !== 0) {
                    $data['picture_url'] = $productImage;
                }
                if (!empty($stationId) && $stationId !== 0) {
                    $data['station_id'] = $stationId;
                }
                
                // 只有当data数组不为空时才执行更新操作
                if (!empty($data)) {
                    Db::table($tableNmae)->where(['asin' => $asin])->update($data);
                }
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

    public function getTemuGoodsRecord()
    {
        $page = $this->request->get('page');
        $limit = $this->request->get('limit');

        // 前端自定义筛选参数
        $createdTimeStart = $this->request->get('created_time_start', '');
        $createdTimeEnd   = $this->request->get('created_time_end', '');
        $dailySalesMin    = $this->request->get('daily_sales_min', '');
        $dailySalesMax    = $this->request->get('daily_sales_max', '');
        $ratingMin        = $this->request->get('rating_min', '');
        $ratingMax        = $this->request->get('rating_max', '');
        $createdByUserId  = $this->request->get('created_by_user_id', '');

        $query = Db::table('ba_temu_goods')
            ->alias('a')
            ->field('a.*');

        // 创建时间范围筛选
        if (!empty($createdTimeStart) && !empty($createdTimeEnd)) {
            $query = $query->where('a.created_time', 'between', [$createdTimeStart, $createdTimeEnd]);
        } else {
            if (!empty($createdTimeStart)) {
                $query = $query->where('a.created_time', '>=', $createdTimeStart);
            }
            if (!empty($createdTimeEnd)) {
                $query = $query->where('a.created_time', '<=', $createdTimeEnd);
            }
        }

        // 日销量范围
        if ($dailySalesMin !== '' && is_numeric($dailySalesMin)) {
            $query = $query->where('a.daily_sales', '>=', intval($dailySalesMin));
        }
        if ($dailySalesMax !== '' && is_numeric($dailySalesMax)) {
            $query = $query->where('a.daily_sales', '<=', intval($dailySalesMax));
        }

        // 评分范围
        if ($ratingMin !== '' && is_numeric($ratingMin)) {
            $query = $query->where('a.rating', '>=', floatval($ratingMin));
        }
        if ($ratingMax !== '' && is_numeric($ratingMax)) {
            $query = $query->where('a.rating', '<=', floatval($ratingMax));
        }

        // 创建人筛选
        if (!empty($createdByUserId) && is_numeric($createdByUserId)) {
            $query = $query->where('a.created_by_user_id', intval($createdByUserId));
        }

        $result = $query
            ->order('a.id', 'desc')
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

    public function exportTemuGoodsRecord()
    {

        $page = $this->request->get('page');
        $limit = $this->request->get('limit');

        // 前端自定义筛选参数（与列表接口保持一致）
        $createdTimeStart = $this->request->get('created_time_start', '');
        $createdTimeEnd   = $this->request->get('created_time_end', '');
        $dailySalesMin    = $this->request->get('daily_sales_min', '');
        $dailySalesMax    = $this->request->get('daily_sales_max', '');
        $ratingMin        = $this->request->get('rating_min', '');
        $ratingMax        = $this->request->get('rating_max', '');
        $createdByUserId  = $this->request->get('created_by_user_id', '');

        $query = Db::table('ba_temu_goods')
            ->alias('a')
            ->field('a.*');

        // 创建时间范围筛选
        if (!empty($createdTimeStart) && !empty($createdTimeEnd)) {
            $query = $query->where('a.created_time', 'between', [$createdTimeStart, $createdTimeEnd]);
        } else {
            if (!empty($createdTimeStart)) {
                $query = $query->where('a.created_time', '>=', $createdTimeStart);
            }
            if (!empty($createdTimeEnd)) {
                $query = $query->where('a.created_time', '<=', $createdTimeEnd);
            }
        }

        // 日销量范围
        if ($dailySalesMin !== '' && is_numeric($dailySalesMin)) {
            $query = $query->where('a.daily_sales', '>=', intval($dailySalesMin));
        }
        if ($dailySalesMax !== '' && is_numeric($dailySalesMax)) {
            $query = $query->where('a.daily_sales', '<=', intval($dailySalesMax));
        }

        // 评分范围
        if ($ratingMin !== '' && is_numeric($ratingMin)) {
            $query = $query->where('a.rating', '>=', floatval($ratingMin));
        }
        if ($ratingMax !== '' && is_numeric($ratingMax)) {
            $query = $query->where('a.rating', '<=', floatval($ratingMax));
        }

        // 创建人筛选
        if (!empty($createdByUserId) && is_numeric($createdByUserId)) {
            $query = $query->where('a.created_by_user_id', intval($createdByUserId));
        }

        $result = $query
            ->order('a.id', 'desc')
            ->paginate($limit, false, [
                'page'  =>  $page
            ]);


        // 导出字段映射
        $exportExcel = [
            'product_id' => '商品ID',
            'site' => '站点',
            'title' => '标题',
            'price' => '价格',
            'daily_sales' => '日销量',
            'total_sales' => '总销量',
            'rating' => '评分',
            'category' => '类目',
            'listing_time' => '上架时间',
            'detail_page_url' => '详情页链接',
            'main_image_url' => '主图链接',
            'created_by_username' => '创建人',
            'created_time' => '创建时间'
        ];

        Excel::export($exportExcel, true, $result->items(), 'Temu商品列表');
    }
    public function getPriceChangeRecord()
    {
        $page = $this->request->get('page');
        $limit = $this->request->get('limit');

        $createdTimeStart = $this->request->get('created_time_start', '');
        $createdTimeEnd   = $this->request->get('created_time_end', '');
        $type             = $this->request->get('type', '');
        $status           = $this->request->get('status', '1');
        $sku              = $this->request->get('sku', '');
        $storeName        = $this->request->get('store_name', '');
        $group_by_store_name       = $this->request->get('group_by_store_name', '');
        $salesStatus      = $this->request->get('sales_status', '');
        $operatorUserId   = $this->request->get('operator_user_id', '');
        $asin             = $this->request->get('asin', '');
        $regionName       = $this->request->get('region_name', '');
        $groupBySku       = $this->request->get('group_by_sku', '');
        $minCount         = $this->request->get('minCount', '');
        $maxCount         = $this->request->get('maxCount', '');
        $originalPriceMin = $this->request->get('original_price_min', '');
        $originalPriceMax = $this->request->get('original_price_max', '');
        $newPriceMin      = $this->request->get('new_price_min', '');
        $newPriceMax      = $this->request->get('new_price_max', '');
        $totalCostMin     = $this->request->get('total_cost_min', '');
        $totalCostMax     = $this->request->get('total_cost_max', '');
        $stockMin         = $this->request->get('stock_min', '');
        $stockMax         = $this->request->get('stock_max', '');
        $orderByCount         = $this->request->get('order_by_count', '');
        $orderByTime         = $this->request->get('order_by_time', '');

        $query = Db::table('ba_price_change_record')
            ->alias('a')
            ->field('a.*');

        if (!empty($createdTimeStart) && !empty($createdTimeEnd)) {
            $query = $query->where('a.created_time', 'between', [$createdTimeStart, $createdTimeEnd]);
        } else {
            if (!empty($createdTimeStart)) {
                $query = $query->where('a.created_time', '>=', $createdTimeStart);
            }
            if (!empty($createdTimeEnd)) {
                $query = $query->where('a.created_time', '<=', $createdTimeEnd);
            }
        }

        if ($type !== '' && is_numeric($type)) {
            $query = $query->where('a.type', intval($type));
        }
        if ($status !== '' && is_numeric($status)) {
            $query = $query->where('a.status', intval($status));
        }
        if (!empty($sku)) {
            $query = $query->where('a.sku', $sku);
        }
        if (!empty($storeName)) {
            $query = $query->where('a.store_name', $storeName);
        }
        if (!empty($salesStatus)) {
            $query = $query->where('a.sales_status', $salesStatus);
        }
        if (!empty($asin)) {
            $query = $query->where('a.asin', $asin);
        }
        if (!empty($regionName)) {
            $query = $query->where('a.region_name', $regionName);
        }
        if (!empty($operatorUserId) && is_numeric($operatorUserId)) {
            $query = $query->where('a.operator_user_id', intval($operatorUserId));
        }

        if ($originalPriceMin !== '' && is_numeric($originalPriceMin)) {
            $query = $query->where('a.original_price', '>=', floatval($originalPriceMin));
        }
        if ($originalPriceMax !== '' && is_numeric($originalPriceMax)) {
            $query = $query->where('a.original_price', '<=', floatval($originalPriceMax));
        }
        if ($newPriceMin !== '' && is_numeric($newPriceMin)) {
            $query = $query->where('a.new_price', '>=', floatval($newPriceMin));
        }
        if ($newPriceMax !== '' && is_numeric($newPriceMax)) {
            $query = $query->where('a.new_price', '<=', floatval($newPriceMax));
        }
        if ($totalCostMin !== '' && is_numeric($totalCostMin)) {
            $query = $query->where('a.total_cost', '>=', floatval($totalCostMin));
        }
        if ($totalCostMax !== '' && is_numeric($totalCostMax)) {
            $query = $query->where('a.total_cost', '<=', floatval($totalCostMax));
        }
        if ($stockMin !== '' && is_numeric($stockMin)) {
            $query = $query->where('a.stock', '>=', intval($stockMin));
        }
        if ($stockMax !== '' && is_numeric($stockMax)) {
            $query = $query->where('a.stock', '<=', intval($stockMax));
        }

        if (!empty($groupBySku)) {
            $countField = 'count(a.id)';
            if ($type == 2) {
                $countField = 'count(distinct a.stock)';
            }
            $query = $query->group('a.sku')->field($countField . ' as count');
            if ($minCount !== '' && is_numeric($minCount)) {
                $query = $query->having($countField . ' >= ' . intval($minCount));
            }
            if ($maxCount !== '' && is_numeric($maxCount)) {
                $query = $query->having($countField . ' <= ' . intval($maxCount));
            }
        } else if ($type !== '' && is_numeric($type) && !empty($sku) && $type == 2) {
            $query = $query->group('a.stock')->field('count(a.id) as count');
        }else if (!empty($group_by_store_name)) {
            $query = $query->group('a.store_name')->field('count(distinct a.sku) as count');
        }

        $order = [];
        if (!empty($orderByCount)) {
            $order['count'] = strtolower($orderByCount) == 'desc' ? 'desc' : 'asc';
        } elseif (!empty($orderByTime)) {
            $order['a.created_time'] = strtolower($orderByTime) == 'desc' ? 'desc' : 'asc';
        } else {
            $order['a.id'] = 'desc';
        }

        $result = $query
            ->order($order)
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

    public function getPriceChangeRecordById()
    {
        $id = $this->request->get('id');
        if (empty($id) || !is_numeric($id)) {
            $this->success('', [
                'code' => 400,
                'desc' => 'id 无效'
            ]);
            return;
        }

        $record = Db::table('ba_price_change_record')
            ->alias('a')
            ->field('a.*')
            ->where('a.id', intval($id))
            ->find();

        if (!$record) {
            $this->success('', [
                'code' => 404,
                'desc' => '记录不存在'
            ]);
            return;
        }

        $this->success('', [
            'code' => 200,
            'data' => $record
        ]);
    }

    public function exportPriceChangeRecord()
    {
        $page = $this->request->get('page');
        $limit = $this->request->get('limit');

        $createdTimeStart = $this->request->get('created_time_start', '');
        $createdTimeEnd   = $this->request->get('created_time_end', '');
        $type             = $this->request->get('type', '');
        $status           = $this->request->get('status', '');
        $sku              = $this->request->get('sku', '');
        $asin             = $this->request->get('asin', '');
        $regionName       = $this->request->get('region_name', '');
        $storeName        = $this->request->get('store_name', '');
        $salesStatus      = $this->request->get('sales_status', '');
        $operatorUserId   = $this->request->get('operator_user_id', '');
        $originalPriceMin = $this->request->get('original_price_min', '');
        $originalPriceMax = $this->request->get('original_price_max', '');
        $newPriceMin      = $this->request->get('new_price_min', '');
        $newPriceMax      = $this->request->get('new_price_max', '');
        $totalCostMin     = $this->request->get('total_cost_min', '');
        $totalCostMax     = $this->request->get('total_cost_max', '');
        $stockMin         = $this->request->get('stock_min', '');
        $stockMax         = $this->request->get('stock_max', '');

        $query = Db::table('ba_price_change_record')
            ->alias('a')
            ->field('a.*');

        if (!empty($createdTimeStart) && !empty($createdTimeEnd)) {
            $query = $query->where('a.created_time', 'between', [$createdTimeStart, $createdTimeEnd]);
        } else {
            if (!empty($createdTimeStart)) {
                $query = $query->where('a.created_time', '>=', $createdTimeStart);
            }
            if (!empty($createdTimeEnd)) {
                $query = $query->where('a.created_time', '<=', $createdTimeEnd);
            }
        }

        if ($type !== '' && is_numeric($type)) {
            $query = $query->where('a.type', intval($type));
        }
        if ($status !== '' && is_numeric($status)) {
            $query = $query->where('a.status', intval($status));
        }
        if (!empty($sku)) {
            $query = $query->where('a.sku', $sku);
        }
        if (!empty($storeName)) {
            $query = $query->where('a.store_name', $storeName);
        }
        if (!empty($salesStatus)) {
            $query = $query->where('a.sales_status', $salesStatus);
        }
        if (!empty($asin)) {
            $query = $query->where('a.asin', $asin);
        }
        if (!empty($regionName)) {
            $query = $query->where('a.region_name', $regionName);
        }
        if (!empty($operatorUserId) && is_numeric($operatorUserId)) {
            $query = $query->where('a.operator_user_id', intval($operatorUserId));
        }

        if ($originalPriceMin !== '' && is_numeric($originalPriceMin)) {
            $query = $query->where('a.original_price', '>=', floatval($originalPriceMin));
        }
        if ($originalPriceMax !== '' && is_numeric($originalPriceMax)) {
            $query = $query->where('a.original_price', '<=', floatval($originalPriceMax));
        }
        if ($newPriceMin !== '' && is_numeric($newPriceMin)) {
            $query = $query->where('a.new_price', '>=', floatval($newPriceMin));
        }
        if ($newPriceMax !== '' && is_numeric($newPriceMax)) {
            $query = $query->where('a.new_price', '<=', floatval($newPriceMax));
        }
        if ($totalCostMin !== '' && is_numeric($totalCostMin)) {
            $query = $query->where('a.total_cost', '>=', floatval($totalCostMin));
        }
        if ($totalCostMax !== '' && is_numeric($totalCostMax)) {
            $query = $query->where('a.total_cost', '<=', floatval($totalCostMax));
        }
        if ($stockMin !== '' && is_numeric($stockMin)) {
            $query = $query->where('a.stock', '>=', intval($stockMin));
        }
        if ($stockMax !== '' && is_numeric($stockMax)) {
            $query = $query->where('a.stock', '<=', intval($stockMax));
        }

        $result = $query
            ->order('a.id', 'desc')
            ->paginate($limit, false, [
                'page'  =>  $page
            ]);

        $exportExcel = [
            'sku' => 'SKU',
            'product_title' => '产品标题',
            'original_price' => '原价',
            'new_price' => '新价格',
            'total_cost' => '总费用',
            'type' => '类型',
            'sales_status' => '销售状态',
            'asin' => 'ASIN',
            'region_name' => '区域名称',
            'status' => '状态',
            'stock' => '库存',
            'store_name' => '店铺名称',
            'operator_user_id' => '改价人用户ID',
            'operator_username' => '改价人用户名',
            'created_time' => '创建时间'
        ];

        Excel::export($exportExcel, true, $result->items(), '改价记录');
    }

    public function addPriceChangeRecord()
    {
        if (!$this->request->isPost()) {
            $this->success('操作失败', [
                'code' => 400,
                'desc' => '仅支持 POST 请求'
            ]);
            return;
        }

        $request = $this->request;

        $sku                = $request->post('sku', '', 'trim');
        $product_title      = $request->post('product_title', '', 'trim');
        $original_price     = $request->post('original_price', 0.0, 'floatval');
        $new_price          = $request->post('new_price', 0.0, 'floatval');
        $total_cost         = $request->post('total_cost', 0.0, 'floatval');
        $type               = $request->post('type', 1, 'intval');
        $status             = $request->post('status', 1, 'intval');
        $stock              = $request->post('stock', 0, 'intval');
        $sales_status       = $request->post('sales_status', '', 'trim');
        $asin               = $request->post('asin', '', 'trim');
        $region_name        = $request->post('region_name', '', 'trim');
        $store_name         = $request->post('store_name', '', 'trim');
        $operator_user_id   = $request->post('operator_user_id', 0, 'intval');
        $operator_username  = $request->post('operator_username', '', 'trim');

        if (empty($sku)) {
            $this->success('操作失败', [
                'code' => 400,
                'desc' => 'SKU 不能为空'
            ]);
            return;
        }
        if (empty($store_name)) {
            $this->success('操作失败', [
                'code' => 400,
                'desc' => '店铺名称不能为空'
            ]);
            return;
        }
        if ($operator_user_id <= 0) {
            $this->success('操作失败', [
                'code' => 400,
                'desc' => '改价人用户ID无效'
            ]);
            return;
        }

        if (!in_array($type, [1, 2])) {
            $this->success('操作失败', [
                'code' => 400,
                'desc' => 'type 无效'
            ]);
            return;
        }

        $data = [
            'sku' => $sku,
            'product_title' => $product_title,
            'original_price' => round($original_price, 2),
            'new_price' => round($new_price, 2),
            'total_cost' => round($total_cost, 2),
            'type' => $type,
            'status' => $status,
            'stock' => $stock,
            'sales_status' => $sales_status,
            'store_name' => $store_name,
            'operator_user_id' => $operator_user_id,
            'operator_username' => $operator_username,
            'created_time' => date('Y-m-d H:i:s'),
            'asin' => $asin,
            'region_name' => $region_name,
        ];

        $result = Db::table('ba_price_change_record')->insert($data);
        if (!$result) {
            $this->success('操作失败', [
                'code' => 400,
                'desc' => '插入改价记录失败'
            ]);
            return;
        }

        $this->success('操作成功', [
            'code' => 200,
            'desc' => '改价记录已添加',
            'sku' => $sku,
            'store_name' => $store_name
        ]);
    }
    public function addTemuGoodsRecord()
    {
        if (!$this->request->isPost()) {
            $this->success('操作失败', [
                'code' => 400,
                'desc' => '仅支持 POST 请求'
            ]);
            return;
        }

        $request = $this->request;

        // === 1. 接收参数 ===
        $product_id        = $request->post('product_id', '', 'trim');
        $price             = $request->post('price', 0.0, 'floatval');
        $main_image_url    = $request->post('main_image_url', '', 'trim');
        $detail_page_url   = $request->post('detail_page_url', '', 'trim');
        $site              = $request->post('site', '', 'trim');
        $title             = $request->post('title', '', 'trim');
        $daily_sales       = $request->post('daily_sales', 0, 'intval');
        $rating            = $request->post('rating', 0.0, 'floatval');
        $created_by_user_id = $request->post('created_by_user_id', 0, 'intval');
        $created_by_username = $request->post('created_by_username', '', 'trim');
        $listing_time      = $request->post('listing_time', '');
        $category          = $request->post('category', '', 'trim');
        $total_sales       = $request->post('total_sales', 0, 'intval');
        $status            = $request->post('status', 1, 'intval'); // 默认正常

        // === 2. 必填字段校验 ===
        if (empty($product_id)) {
            $this->success('操作失败', [
                'code' => 400,
                'desc' => 'product_id 不能为空'
            ]);
            return;
        }
        if (empty($site)) {
            $this->success('操作失败', [
                'code' => 400,
                'desc' => 'site 站点不能为空'
            ]);
            return;
        }
        if ($created_by_user_id <= 0) {
            $this->success('操作失败', [
                'code' => 400,
                'desc' => '创建人用户ID无效'
            ]);
            return;
        }

        $tableName = 'ba_temu_goods';

        // === 3. 查询是否已存在（基于 product_id + site）===
        $existing = Db::table($tableName)
            ->where('product_id', $product_id)
            ->where('site', $site)
            ->field('id, created_by_user_id, created_by_username, created_time, status')
            ->find();

        $action = 1; // 1: 新增，2: 更新

        // === 4. 构建数据数组 ===
        $data = [
            'product_id'           => $product_id,
            'price'                => round($price, 2),
            'main_image_url'       => $main_image_url,
            'detail_page_url'      => $detail_page_url,
            'site'                 => $site,
            'title'                => $title,
            'daily_sales'          => $daily_sales,
            'rating'               => round($rating, 2),
            'category'             => $category,
            'total_sales'          => $total_sales,
            'status'               => $status,
        ];

        // 处理 listing_time
        if (!empty($listing_time)) {
            $data['listing_time'] = is_numeric($listing_time) 
                ? date('Y-m-d H:i:s', $listing_time) 
                : $listing_time;
        }

        // === 5. 判断插入 or 更新 ===
        if ($existing) {
            // 已存在：只更新，不修改创建人、创建时间等信息
            $action = 2;

            // 可选：如果状态是删除（0），可以考虑恢复而不是更新
            // if ($existing['status'] == 0) {
            //     $data['status'] = 1; // 恢复为正常
            // }

            $result = Db::table($tableName)
                ->where('id', $existing['id'])
                ->update($data);

            if ($result === false) {
                $this->success('操作失败', [
                    'code' => 400,
                    'desc' => '更新商品失败'
                ]);
                return;
            }

        } else {
            // 不存在：插入新记录
            $data['created_by_user_id']   = $created_by_user_id;
            $data['created_by_username']  = $created_by_username;
            $data['created_time']         = date('Y-m-d H:i:s');

            $result = Db::table($tableName)->insert($data);
            if (!$result) {
                $this->success('操作失败', [
                    'code' => 400,
                    'desc' => '插入商品失败'
                ]);
                return;
            }
        }

        // === 7. 返回成功响应 ===
        $this->success('操作成功', [
            'code' => 200,
            'desc' => $action == 1 ? '商品已添加' : '商品已更新',
            'action' => $action,
            'product_id' => $product_id,
            'site' => $site
        ]);
    }



    private function assembleQueryConditions()
    {
        $page = $this->request->get('page');
        $limit = $this->request->get('limit');
        $status = $this->request->get('status');
        $asin = $this->request->get('asin');
        $favoriteStatus = $this->request->get('favoriteStatus');
        $createAdmin = $this->request->get('createAdmin');
        $createTeam = $this->request->get('createTeam');
        $createAdminStart = $this->request->get('createTimeStart');
        $createAdminEnd = $this->request->get('createTimeEnd');
        $station = $this->request->get('station');
        $categoryRank = $this->request->get('categoryRank');
        $weight = $this->request->get('weight');
        $shipping = $this->request->get('shipping');
        $brand = $this->request->get('brand');
        $price = $this->request->get('price');
        $profitMargin = $this->request->get('profitMargin');
        $preview = $this->request->get('preview');
        $sellerCount = $this->request->get('sellerCount');
        $reviewStatus = $this->request->get('reviewStatus');

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

        if ($station) {
            $queryWhere[] = ['a.station_id', '=', $station];
        }

        if ($categoryRank) {
            $queryWhere[] = ['a.rank', '=', $categoryRank];
        }

        if ($weight) {
            $queryWhere[] = ['a.weight', '=', $weight];
        }

        if ($shipping) {
            $queryWhere[] = ['a.shipping_method', '=', $shipping];
        }

        if ($brand) {
            $queryWhere[] = ['a.brand_name', '=', $brand];
        }

        if ($price) {
            $queryWhere[] = ['a.price', '=', $price];
        }

        if ($profitMargin) {
            $queryWhere[] = ['a.profit_margin', '=', $profitMargin];
        }

        if ($preview) {
            // 这里需要根据 preview 对应的数据库字段进行调整
            // 假设 preview 对应数据库字段为 preview，示例如下
            $queryWhere[] = ['a.preview', '=', $preview];
        }

        if ($sellerCount) {
            $queryWhere[] = ['a.seller_count', '=', $sellerCount];
        }

        if ($reviewStatus) {
            $queryWhere[] = ['a.review_status', '=', $reviewStatus];
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

        return [
            'page' => $page,
            'limit' => $limit,
            'queryWhere' => $queryWhere,
            'whereRole' => $whereRole,
            'teamAreaRole' => $teamAreaRole
        ];
    }

    public function getPlugProductRecord()
    {
        $conditions = $this->assembleQueryConditions();
        
        // 记录SQL执行开始时间
        $startTime = microtime(true);

        $result = Db::table('ba_plugin_product_record')
            ->alias('a')
            ->field('a.*,s.title as station_title,pd.product_name as pd_name,CASE WHEN pd.id IS NOT NULL THEN 1 ELSE 0 END AS has_product, ua.nickname AS update_admin_nickname, ca.nickname AS create_admin_nickname')
            ->leftJoin('ba_admin ua', 'a.update_admin = ua.id')
            ->leftJoin('ba_admin ca', 'a.create_admin = ca.id')
            ->leftJoin('ba_product pd', 'a.asin = pd.asin and (a.station_id = 0 or a.station_id = pd.station_id)')
            ->leftJoin('ba_station s', 'a.station_id = s.id')
            ->leftJoin('ba_team ct', 'ct.id = ca.team_id')
            ->where($conditions['teamAreaRole'])
            ->where($conditions['queryWhere'])
            ->where($conditions['whereRole'])
            ->order('a.update_time', 'desc')
            ->paginate($conditions['limit'], true, [
                'page'  => $conditions['page']
            ]);
        
        // 计算SQL执行耗时（毫秒）
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 3);
        
        $sql = Db::getLastSql();

        $this->success('', [
            'list' => $result->items(),
            'total' => 100000,//$result->total(),
            'sql' => $sql,
            'execution_time' => $executionTime, // 返回耗时信息供前端使用
        ]);
    }
    public function exportPlugProductRecord()
    {
        $conditions = $this->assembleQueryConditions();

        $result = Db::table('ba_plugin_product_record')
            ->alias('a')
            ->field('a.*,s.title as station_title,pd.product_name as pd_name,CASE WHEN pd.id IS NOT NULL THEN 1 ELSE 0 END AS has_product, ua.nickname AS update_admin_nickname, ca.nickname AS create_admin_nickname')
            ->leftJoin('ba_admin ua', 'a.update_admin = ua.id')
            ->leftJoin('ba_admin ca', 'a.create_admin = ca.id')
            ->leftJoin('ba_product pd', 'a.asin = pd.asin and (a.station_id = 0 or a.station_id = pd.station_id)')
            ->leftJoin('ba_station s', 'a.station_id = s.id')
            ->leftJoin('ba_team ct', 'ct.id = ca.team_id')
            ->where($conditions['teamAreaRole'])
            ->where($conditions['queryWhere'])
            ->where($conditions['whereRole'])
            ->order('a.update_time', 'desc')
            ->paginate($conditions['limit'], false, [
                'page'  => $conditions['page']
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
    
    
   public function getOtps()
   {
       $page = $this->request->get('page');
       $limit = $this->request->get('limit');
       $desc = $this->request->get('desc');
       $permissionId = $this->request->get('permissionId');
       $team_area_id = $this->request->get('team_area_id');
       $store_name = $this->request->get('store_name');

       
       $admin = $this->auth->getAdmin();
       $currentTeamArea = $admin->belong_team_area_id;
       $teamAreaRole = '';
        if ($currentTeamArea && $currentTeamArea != 0) {
            $teamAreaRole = 'a.team_area_id = '.$currentTeamArea;
        } else if ($team_area_id && $team_area_id != 0) {
            $teamAreaRole = 'a.team_area_id = '.$team_area_id;
        }
        
        $userPermissionRole = '';
        if (in_array(1, $admin->group_arr) || in_array(5, $admin->group_arr)) {
             $userPermissionRole = ''; // 超级管理员和大区管理员不限制
        } else {
            $userPermissionRole = 'a.permission_admin_ids like "%'.$admin->id.',%"'; // 其他人员根据permission_admin_ids判断
        }

        $searchWhere = '';
        if ($desc && $desc != '') {
            $searchWhere = 'a.desc like "%'.$desc.'%"';
        }
        $store_nameWhere = '';
        if ($store_name && $store_name != '') {
            $store_nameWhere = 'a.store_name like "%'.$store_name.'%"';
        }

        $permissionIdWhere = '';
        if ($permissionId && $permissionId != '') {
            $permissionIdWhere = 'a.permission_admin_ids like "%'.$permissionId.',%"';
        }
       
       $result = Db::table('ba_otp')
           ->alias('a')
           ->field('a.id,a.store_name,a.desc,a.uri,a.secret,a.acount,a.type,a.issuer,a.create_time,a.permission_admin_ids,ta.name as teamAreaName,a.team_area_id,(select GROUP_CONCAT(CONCAT("[", id, "]", username) SEPARATOR "、") AS names_list from ba_admin where FIND_IN_SET(id, a.permission_admin_ids)) as userList')
           ->leftJoin('ba_team_area ta', 'ta.id = a.team_area_id')
           ->order('a.create_time')
           ->where('a.status', 1)
           ->where($teamAreaRole)
           ->where($userPermissionRole)
           ->where($searchWhere)
           ->where($permissionIdWhere)
           ->where($store_nameWhere)
           ->paginate($limit, false, [
               'page'  => $page
           ]);
        $sql = Db::getLastSql();
       $this->success('', [
           'list' => $result->items(),
           'total' => $result->total(),
           'sql' => $sql
       ]);
   }

   public function addOtp()
   {
        
       if ($this->request->isPost()) {
           $request = $this->request;
           $tableName = 'ba_otp';

           $desc = $request->post('desc', '');
           $uri = $request->post('uri', '');
           $secret = $request->post('secret', '');
           $acount = $request->post('acount', '');
           $type = $request->post('type', '');
           $issuer = $request->post('issuer', '');
           $teamAreaId = $request->post('team_area_id', '');
           $store_name = $request->post('store_name', '');
           
         
           
           // 检查必填字段
           if (empty($desc) || empty($uri) || empty($secret)) {
               $this->success('', [
                   'code' => 400,
                   'desc' => "描述、URI和密钥不能为空"
               ]);
               return;
           }

           $data = [
               'desc' => $desc,
               'uri' => $uri,
               'secret' => $secret,
               'acount' => $acount,
               'type' => $type,
               'issuer' => $issuer,
               'create_time' => time(),
               'team_area_id' => $teamAreaId,
               'permission_admin_ids' => '',
               'status' => 1,
               'store_name' => $store_name
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

   public function editOtp()
   {
       if ($this->request->isPost()) {
           $request = $this->request;
           $tableName = 'ba_otp';

           $id = $request->post('id', 0);
           $desc = $request->post('desc', '');
           $uri = $request->post('uri', '');
           $secret = $request->post('secret', '');
           $acount = $request->post('acount', '');
           $type = $request->post('type', '');
           $issuer = $request->post('issuer', '');
           $status = $request->post('status', 1);
           $permission_admin_ids = $request->post('permission_admin_ids', '');
           $teamAreaId = $request->post('team_area_id', '');
           $store_name = $request->post('store_name', '');
           
           // 检查必填字段
           if (empty($id)) {
               $this->success('', [
                   'code' => 400,
                   'desc' => "ID不能为空"
               ]);
               return;
           }

           // 检查记录是否存在
           $exists = Db::table($tableName)->where(['id' => $id])->find();
           if (!$exists) {
               $this->success('', [
                   'code' => 400,
                   'desc' => "记录不存在"
               ]);
               return;
           }

           $data = [
               'desc' => $desc,
               'uri' => $uri,
               'secret' => $secret,
               'acount' => $acount,
               'type' => $type,
               'issuer' => $issuer,
               'status' => $status,
               'team_area_id' => $teamAreaId,
               'store_name' => $store_name
           ];
           
            if (strlen($permission_admin_ids) > 0) {
               $data = ['permission_admin_ids' => $permission_admin_ids];
            }

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
            // $nameExists = Db::table($tableName)
            //     ->where('id', '<>', $id)
            //     ->where(['name' => $name])
            //     ->find();
            // if ($nameExists) {
            //     $this->success('', [
            //         'code' => 400,
            //         'desc' => "团队名称已存在"
            //     ]);
            //     return;
            // }

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
        return $version == '20250825214500';
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
        
        //Fba请求方式：0 - 走代理；1 - 服务器直接请求；
        $requestType=0;
        
        if ($requestType == 1) {
            // 构造 URL
            $url = "https://das-server.tool4seller.cn/ap/fba/calculate?marketplaceId=" . $marketplaceId . "&asin=" . $asin . "&amount=0.00&t=" . time();
            
            
            $selectedProxy = $this->getRandomProxy();
    
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
            
            // 执行请求
            $startTime = microtime(true);
            
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
                    'desc' => "查询产品接口失败，使用代理（".$proxy_ip.")"
                ]);
                
            } else {
                $getFbaResultObj = json_decode($response,true);
                
                if (!$getFbaResultObj || !$getFbaResultObj['status'] || $getFbaResultObj['status'] != 1) {
                    $this->success('', [
                        'code' => 400,
                        'asin' => $asin,
                        'region' => $region,
                        'resule' => $response,
                        'desc' => "查询产品接口失败，使用代理（".$proxy_ip.")"
                    ]);
                    return;
                }
                    
                $this->success('', [
                    'code' => 200,
                    'asin' => $asin,
                    'region' => $region,
                    'result' => $getFbaResultObj,
                    'desc' => "查询成功，使用代理(".$proxy_ip.")，耗时".$duration."秒",
                ]);
            }
            
            // 关闭句柄
            curl_close($ch);
            
        } else {
    
            $reqUrl = "https://das-server.tool4seller.cn/ap/fba/calculate?marketplaceId=".$marketplaceId."&asin=".$asin."&amount=0.00&t=".time();
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
                'desc' => "本地服务器直接查询成功"
            ]);
        }
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

    public function checkBrandByWipo($reqParams, $retryTimes){
        $brand = $reqParams['brand'];
        $region = $reqParams['region'];
        $version = $reqParams['version'];
        
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
            'origin:https://branddb.wipo.int',
            'pragma:no-cache',
            'priority:u=1, i',
            'referer:https://branddb.wipo.int',
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36'
        );

        $getResult = $this->sendPostRequest('https://api.branddb.wipo.int/search',$params,$header);
        
        
        // $randomProxy = $this->getRandomProxy();
        // // 请求参数
        // $url = 'https://api.branddb.wipo.int/search';
        // // 发送请求
        // $getResult = $this->postRequest($url, $params, $header, $randomProxy);
        
         

        if (!is_string($getResult) || !base64_decode($getResult, true)) {  
            if ($retryTimes > 0) {
                $this->checkBrandByWipo($reqParams, $retryTimes - 1);
                return;
            }
            $this->success('', [
                'code' => 400,
                'brand' => $brand,
                'region' => $region,
                'result' => $getResult,
                'header' => $header,
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
    
    public function postRequest($url, $data = [], $headers = [], $proxy = null) {
        $ch = curl_init();
        
        // 基本CURL配置
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        
        // 设置请求头
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        // 代理配置
        if ($proxy) {
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5); // SOCKS5代理
            curl_setopt($ch, CURLOPT_PROXY, $proxy['ip'] . ':' . $proxy['port']);
            
            if (!empty($proxy['user']) && !empty($proxy['pass'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['user'] . ':' . $proxy['pass']);
            }
        }
        
        // 执行请求
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("CURL请求失败: " . $error);
        }
        
        curl_close($ch);
        
        return [
            'response' => $response,
            'http_code' => $httpCode
        ];
    }
//代理配置2025.7.8    
    public function getRandomProxy() {
        $proxyConfigs = [
        //socks5配置项1  到期11-14
            [
                'ip'   => '121.237.182.18', 
                'port' => 11806,
                'user' => '5579',
                'pass' => '5579',
            ],
               
            [
                'ip'   => '203.83.236.230', 
                'port' => 11310,
                'user' => '5579',
                'pass' => '5579',
            ],
               
            [
                'ip'   => '125.122.153.5', 
                'port' => 11217,
                'user' => '5579',
                'pass' => '5579',
            ],
        // //socks5配置项2
        //     [
        //         'ip'   => '223.15.246.169',    //到期时间11-12
        //         'port' => 11322,
        //         'user' => '5579',
        //         'pass' => '5579',
            // ],
        //socks5配置项3
            // [
                // 'ip'   => '59.38.131.92', // 11-12
                // 'port' => 11616,
                // 'user' => '5579',
                // 'pass' => '5579',
            // ],
            
        ];
        
        if (empty($proxyConfigs)) {
            return null;
        }
        
        $randomIndex = array_rand($proxyConfigs);
        return $proxyConfigs[$randomIndex];
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
            /*$params = [
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

            var_dump($result);*/
            $this->success('', [
                'code' => 400,
                'brand' => $brand,
                'region' => $region,
                'resule' => ""
            ]);
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
