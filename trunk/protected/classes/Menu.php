<?php

//系统菜单类
class Menu {

    private $nodes;
    private $subMenu;
    private $menu;
    private $_menu;
    private $_subMenu;
    private $link_key;

    public function __construct() {
        $nodes = array(
            '/admin/index' => array('name' => '管理首页', 'parent' => 'config'),
            '/admin/theme_list' => array('name' => '主题设置', 'parent' => 'config'),
            '/admin/config_globals' => array('name' => '站点设置', 'parent' => 'config'),
            '/admin/config_other' => array('name' => '其它配置', 'parent' => 'config'),
            '/admin/config_upyun' => array('name' => '又拍云配置', 'parent' => 'config'),
            '/admin/config_email' => array('name' => '邮箱配置', 'parent' => 'config'),
            '/admin/msg_template_list' => array('name' => '信息模板', 'parent' => 'config'),
            '/admin/msg_template_edit' => array('name' => '信息模板编辑', 'parent' => 'config'),
            '/admin/notice_template_list' => array('name' => '提醒管理', 'parent' => 'config'),
            '/admin/notice_template_edit' => array('name' => '提醒编辑', 'parent' => 'config'),
            '/admin/version_list' => array('name' => '版本更新', 'parent' => 'config'),
            '/admin/version_edit' => array('name' => '版本更新编辑', 'parent' => 'config', 'hidden'=>true),
            '/admin/version_add' => array('name' => '添加版本更新', 'parent' => 'config', 'hidden'=>true),
            '/admin/oauth_list' => array('name' => '开放登录', 'parent' => 'third'),
            '/admin/oauth_edit' => array('name' => '开放登录编辑', 'parent' => 'third'),
            '/admin/class_config_list' => array('name' => '第三方列表', 'parent' => 'third'),
            '/admin/class_config_edit' => array('name' => '第三方配制编辑', 'parent' => 'third'),
            '/admin/payment_list' => array('name' => '支付方式', 'parent' => 'delivery'),
            '/admin/third_payment' => array('name' => '第三方支付', 'parent' => 'delivery'),
            '/admin/payment_edit' => array('name' => '编辑支付方式', 'parent' => 'delivery'),
            '/admin/zoning_list' => array('name' => '区域划分', 'parent' => 'delivery'),
            '/admin/area_list' => array('name' => '地区管理', 'parent' => 'delivery'),
            '/admin/fare_list' => array('name' => '运费模板', 'parent' => 'delivery'),
            '/admin/fare_edit' => array('name' => '运费模板编辑', 'parent' => 'delivery'),
            '/admin/express_company_list' => array('name' => '快递公司', 'parent' => 'delivery'),
            '/admin/express_company_edit' => array('name' => '快递公司编辑', 'parent' => 'delivery'),
            '/admin/manager_list' => array('name' => '管理员', 'parent' => 'safe'),
            '/admin/manager_edit' => array('name' => '编辑管理员', 'parent' => 'safe'),
            '/admin/roles_list' => array('name' => '角色管理', 'parent' => 'safe'),
            '/admin/roles_edit' => array('name' => '角色编辑', 'parent' => 'safe'),
            '/admin/resources_list' => array('name' => '权限列表', 'parent' => 'safe'),
            '/admin/resources_edit' => array('name' => '编辑权限资源', 'parent' => 'safe'),
            '/admin/log_operation_list' => array('name' => '操作日志', 'parent' => 'safe'),
            '/admin/update' => array('name' => '版本升级', 'parent' => 'safe'),
            '/admin/clear' => array('name' => '清除缓存', 'parent' => 'safe'),
            '/content/article_list' => array('name' => '全部文章', 'parent' => 'article'),
            '/content/article_edit' => array('name' => '文章编辑', 'parent' => 'article'),
            '/content/category_list' => array('name' => '分类管理', 'parent' => 'article'),
            '/content/category_edit' => array('name' => '编辑分类', 'parent' => 'article'),
            '/content/help_list' => array('name' => '全部帮助', 'parent' => 'help'),
            '/content/help_edit' => array('name' => '帮助编辑', 'parent' => 'help'),
            '/content/help_category_list' => array('name' => '帮助分类管理', 'parent' => 'help'),
            '/content/help_category_edit' => array('name' => '编辑帮助分类', 'parent' => 'help'),
            '/content/ad_list' => array('name' => '广告管理', 'parent' => 'banner'),
            '/content/ad_edit' => array('name' => '编辑广告', 'parent' => 'banner'),
            '/content/tags_list' => array('name' => '标签管理', 'parent' => 'banner'),
            '/content/nav_list' => array('name' => '导航管理', 'parent' => 'banner'),
            '/content/nav_edit' => array('name' => '导航管理', 'parent' => 'banner'),
            '/content/uploads_list' => array('name' => '上传文件管理', 'parent' => 'banner'),
            '/admin/tables_list' => array('name' => '数据库备份', 'parent' => 'database'),
            '/admin/back_list' => array('name' => '数据库还原', 'parent' => 'database'),
            '/goods/goods_category_list' => array('name' => '分类管理', 'parent' => 'goods_config'),
            '/goods/goods_category_edit' => array('name' => '编辑分类', 'parent' => 'goods_config'),
            '/goods/goods_type_list' => array('name' => '类型管理', 'parent' => 'goods_config'),
            '/goods/goods_type_edit' => array('name' => '类型编辑', 'parent' => 'goods_config'),
            '/goods/goods_spec_list' => array('name' => '规格管理', 'parent' => 'goods_config'),
            '/goods/goods_spec_edit' => array('name' => '规格编辑', 'parent' => 'goods_config'),
            '/goods/brand_list' => array('name' => '品牌管理', 'parent' => 'goods_config'),
            '/goods/personal_shop_set' => array('name' => '入驻设置', 'parent' => 'personal_shop'),
            '/goods/personal_shop_list' => array('name' => '店铺管理', 'parent' => 'personal_shop'),
            '/goods/brand_edit' => array('name' => '品牌编辑', 'parent' => 'goods_config'),
            '/goods/goods_list' => array('name' => '商品管理', 'parent' => 'goods'),
            '/goods/goods_edit' => array('name' => '商品编辑', 'parent' => 'goods'),
            // '/goods/goods_add' => array('name' => '商品添加', 'parent' => 'goods'),
            '/shop/shop_edit' => array('name' => '商家编辑', 'parent' => 'shop'),
            '/shop/shop_list' => array('name' => '商家管理', 'parent' => 'shop'),
            '/shop/shop_category_edit' => array('name' => '编辑分类', 'parent' => 'shop'),
            '/shop/shop_category_list' => array('name' => '商家分类', 'parent' => 'shop'),
            '/customer/customer_list' => array('name' => '会员管理', 'parent' => 'customer'),
            '/customer/customer_edit' => array('name' => '添加会员', 'parent' => 'customer'),
            '/customer/customer_invite' => array('name' => '会员下线信息', 'parent' => 'customer', 'hidden'=>true),
            '/customer/customer_invited' => array('name' => '会员上线信息', 'parent' => 'customer', 'hidden'=>true),
            '/customer/grade_list' => array('name' => '会员等级管理', 'parent' => 'customer'),
            '/customer/grade_edit' => array('name' => '添加会员等级', 'parent' => 'customer'),
            '/customer/withdraw_list' => array('name' => '提现申请', 'parent' => 'balance'),
            '/customer/balance_list' => array('name' => '余额日志', 'parent' => 'balance'),
            '/customer/pointcoin_list' => array('name' => '积分日志', 'parent' => 'balance'),
            '/customer/review_list' => array('name' => '商品评价', 'parent' => 'ask_reviews'),
            '/customer/ask_list' => array('name' => '商品咨询', 'parent' => 'ask_reviews'),
            '/customer/ask_edit' => array('name' => '咨询回复', 'parent' => 'ask_reviews'),
            '/customer/message_list' => array('name' => '信息管理', 'parent' => 'ask_reviews'),
            '/customer/message_edit' => array('name' => '信息发送', 'parent' => 'ask_reviews'),
            '/customer/notify_list' => array('name' => '到货通知', 'parent' => 'ask_reviews'),
            '/order/order_list' => array('name' => '商品订单', 'parent' => 'order'),
            '/order/offlineorder_list' => array('name' => '线下订单', 'parent' => 'order'),
            '/order/express_template_list' => array('name' => '快递单模板', 'parent' => 'express'),
            '/order/express_template_edit' => array('name' => '快递单模板编辑', 'parent' => 'express'),
            '/order/ship_list' => array('name' => '发货点管理', 'parent' => 'express'),
            '/order/ship_edit' => array('name' => '发货点编辑', 'parent' => 'express'),
            '/order/doc_receiving_list' => array('name' => '商品收款单', 'parent' => 'receipt'),
            '/order/recharge_list' => array('name' => '充值收款单', 'parent' => 'receipt'),
            '/order/doc_invoice_list' => array('name' => '发货单', 'parent' => 'receipt'),
            // '/order/doc_refund_list' => array('name' => '退款单【旧，可能废弃】', 'parent' => 'receipt'),
            '/order/refund_apply_list' => array('name' => '退款申请', 'parent' => 'receipt'),
            //'/order/doc_returns_list'=>array('name'=>'退货单','parent'=>'receipt'),
            '/count/index' => array('name' => '订单统计', 'parent' => 'count'),
            '/count/hot' => array('name' => '热销统计', 'parent' => 'count'),
            '/count/area_buy' => array('name' => '地区统计', 'parent' => 'count'),
            '/count/user_reg' => array('name' => '会员分布统计', 'parent' => 'customer_count'),
            '/count/inventory' => array('name'=>'货品进销存明细表','parent'=>'financial_count'), //货品进销存统计表
            '/count/sales_analysis' => array('name'=>'销售分析表','parent'=>'financial_count'), //货品进销存统计表
            '/count/supplier' => array('name'=>'供应商明细表','parent'=>'financial_count'), //货品进销存统计表
            '/count/division' => array('name'=>'提成划分明细表','parent'=>'financial_count'), //货品进销存统计表
            '/count/sales_rank' => array('name'=>'销售排行榜表','parent'=>'financial_count'), //货品进销存统计表
            '/count/balance_count' => array('name'=>'用户钱袋统计表','parent'=>'financial_count'), //货品进销存统计表
            '/count/balance_account' => array('name'=>'商家入账统计表','parent'=>'financial_count'), //货品进销存统计表
            '/count/order_account' => array('name'=>'订单统计','parent'=>'order_count'), //订单统计表
            '/marketing/voucher_template_list' => array('name' => '代金券模板', 'parent' => 'voucher'),
            '/marketing/voucher_template_edit' => array('name' => '代金券模板编辑', 'parent' => 'voucher'),
            '/marketing/voucher_list' => array('name' => '代金券管理', 'parent' => 'voucher'),
            '/marketing/voucher_edit' => array('name' => '代金券编辑', 'parent' => 'voucher'),
            '/marketing/discount_list' => array('name' => '优惠券管理', 'parent' => 'voucher'),
            '/marketing/discount_edit' => array('name' => '优惠券管理', 'parent' => 'voucher'),
            '/marketing/recharge_package_set' => array('name' => '充值套餐', 'parent' => 'promotions'),
            '/marketing/prom_goods_list' => array('name' => '商品促销', 'parent' => 'promotions'),
            '/marketing/prom_goods_edit' => array('name' => '编辑商品促销', 'parent' => 'promotions'),
            '/marketing/prom_order_list' => array('name' => '订单促销', 'parent' => 'promotions'),
            '/marketing/prom_order_edit' => array('name' => '编辑订单促销', 'parent' => 'promotions'),
            '/marketing/bundling_list' => array('name' => '捆绑促销', 'parent' => 'promotions'),
            '/marketing/bundling_edit' => array('name' => '编辑捆绑促销', 'parent' => 'promotions'),
            '/marketing/groupbuy_list' => array('name' => '团购', 'parent' => 'promotions'),
            '/marketing/groupbuy_edit' => array('name' => '团购', 'parent' => 'promotions'),
            '/marketing/flash_sale_list' => array('name' => '限时抢购', 'parent' => 'promotions'),
            '/marketing/flash_sale_edit' => array('name' => '编辑限时抢购', 'parent' => 'promotions'),
            '/marketing/point_sale_list' => array('name' => '积分购', 'parent' => 'promotions'),
            '/marketing/pointflash_sale_list' => array('name' => '积分抢购', 'parent' => 'promotions'),
            '/marketing/sign_in_set' => array('name' => '每日签到', 'parent' => 'welfare'),
            '/marketing/bonus' => array('name' => '商城分红', 'parent' => 'welfare'),
            '/marketing/redbag_list' => array('name' => '红包列表', 'parent' => 'welfare'),
            '/marketing/index_notice' => array('name' => '通知公告', 'parent' => 'welfare'),
            '/marketing/redbag_edit' => array('name' => '编辑红包', 'parent' => 'welfare', 'hidden'=>true),
            '/marketing/point_sale_edit' => array('name' => '积分购编辑', 'parent' => 'promotions'),
            '/marketing/invite_active' => array('name' => '拉新活动', 'parent' => 'welfare'),
            '/marketing/travel_way' => array('name' => '旅游路线', 'parent' => 'travel'),
            '/marketing/way_edit' => array('name' => '旅游路线', 'parent' => 'travel','hidden'=>true),
            '/marketing/travel_order' => array('name' => '出行订单', 'parent' => 'travel'),
            '/marketing/travel_order_detail' => array('name' => '出行订单详情', 'parent' => 'travel','hidden'=>true),
            '/wxmanager/wx_public_list' => array('name' => '公众号列表', 'parent' => 'weixin'),
            '/wxmanager/wx_public_edit' => array('name' => '公众号编辑', 'parent' => 'weixin'),
            '/wxmanager/menu' => array('name' => '公众号菜单', 'parent' => 'weixin', 'hidden'=>1),
            '/wxmanager/wx_response_list' => array('name' => '资源列表', 'parent' => 'weixin'),
            '/wxmanager/wx_response_edit' => array('name' => '资源编辑', 'parent' => 'weixin'),
            '/support/apply_list'=>array('name'=>'申请列表','parent'=>'support'),
            '/complaint/complaint_list'=>array('name'=>'投诉列表','parent'=>'complaint'),
            '/districtadmin/record_sale'=>array('name'=>'销售记录','parent'=>'record'),
            '/districtadmin/record_income'=>array('name'=>'收益记录','parent'=>'record'),
            '/districtadmin/list_hirer'=>array('name'=>'经销商','parent'=>'personnel'),
            '/districtadmin/list_promoter'=>array('name'=>'代理商','parent'=>'personnel'),
            '/districtadmin/apply_withdraw'=>array('name'=>'提现申请','parent'=>'apply'),
            '/districtadmin/apply_join'=>array('name'=>'入驻申请','parent'=>'apply'),
            '/districtadmin/shop_check'=>array('name'=>'商家认证','parent'=>'apply'),
            '/districtadmin/shop_check_detail'=>array('name'=>'商家认证详情','parent'=>'apply','hidden'=>true),
            // '/districtadmin/qrcode_join'=>array('name'=>'商家二维码申请','parent'=>'apply'),
            '/districtadmin/set'=>array('name'=>'专区设置','parent'=>'set'),
            '/districtadmin/rate_edit' => array('name' => '设置分账比例', 'parent' => 'personnel', 'hidden'=>true),
            '/districtadmin/invitepay' => array('name' => '商家二维码', 'parent' => 'personnel', 'hidden'=>true),
            '/districtadmin/hirer_edit'=> array('name' => '编辑专区名称','parent' =>'personnel', 'hidden'=>true),
            '/districtadmin/shop_child_count'=> array('name' => '下级专区和代理商销售信息','parent' =>'personnel', 'hidden'=>true),
            '/districtadmin/promoter_edit'=> array('name' => '编辑代理商专区名称','parent' =>'personnel', 'hidden'=>true),
            // '/districtadmin/payset'=>array('name'=>'秒到支付','parent'=>'set'),
            '/districtadmin/cashier_list'=> array('name' => '收银员列表','parent' =>'cashier'),
            '/districtadmin/cashier_log'=> array('name' => '收银员上班记录','parent' =>'cashier'),
        );
        //分组菜单
        $subMenu = array(
            'config' => array('name' => '参数设定', 'parent' => 'system'),
            'third' => array('name' => '第三方整合', 'parent' => 'system'),
            'delivery' => array('name' => '支付与配送', 'parent' => 'system'),
            'safe' => array('name' => '安全管理', 'parent' => 'system'),
            'database' => array('name' => '数据库管理', 'parent' => 'system'),
            'article' => array('name' => '文章管理', 'parent' => 'content'),
            'help' => array('name' => '帮助中心', 'parent' => 'content'),
            'banner' => array('name' => '内容管理', 'parent' => 'content'),
            'goods' => array('name' => '产品管理', 'parent' => 'goods'),
            'shop' => array('name' => '商家管理', 'parent' => 'goods'),
            'goods_config' => array('name' => '商品配置', 'parent' => 'goods'),
            'personal_shop' => array('name' => '个人店铺', 'parent' => 'goods'),
            'customer' => array('name' => '会员管理', 'parent' => 'customer'),
            'balance' => array('name' => '会员资金', 'parent' => 'customer'),
            'ask_reviews' => array('name' => '咨询与评价', 'parent' => 'customer'),
            'order' => array('name' => '订单管理', 'parent' => 'order'),
            'receipt' => array('name' => '单据管理', 'parent' => 'order'),
            'express' => array('name' => '快递单配置', 'parent' => 'order'),
            'count' => array('name' => '销售统计', 'parent' => 'count'),
            'customer_count' => array('name' => '客户统计', 'parent' => 'count'),
            'financial_count' => array('name'=> '财务统计', 'parent' => 'count'),
            'order_count' => array('name'=> '订单统计', 'parent' => 'count'),
            'promotions' => array('name' => '促销活动', 'parent' => 'marketing'),
            'voucher' => array('name' => '代金券管理', 'parent' => 'marketing'),
            'welfare' => array('name' => '福利活动', 'parent' => 'marketing'),
            'travel' => array('name' => '旅游活动', 'parent' => 'marketing'),
            'weixin' => array('name' => '微信管理', 'parent' => 'wxmanager'),
            'support'=>array('name'=>'售后管理','parent'=>'support'),
            'complaint'=>array('name'=>'投诉管理','parent'=>'complaint'),
            'commission'=>array('name'=>'佣金记录','parent'=>'commission'),
            'commission_manager'=>array('name'=>'佣金管理','parent'=>'commission'),
            'commission_set'=>array('name'=>'佣金设置','parent'=>'commission'),
            'record'=>array('name'=>'专区记录','parent'=>'districtadmin'),
            'personnel'=>array('name'=>'专区人员','parent'=>'districtadmin'),
            'apply'=>array('name'=>'申请信息','parent'=>'districtadmin'),
            'set'=>array('name'=>'专区配置','parent'=>'districtadmin'),
            'cashier'=>array('name'=>'收银管理','parent'=>'districtadmin'),
        );
        //主菜单
        $menu = array(
            'goods' => array('link' => '/goods/goods_list', 'name' => '商品中心'),
            'order' => array('link' => '/order/order_list', 'name' => '订单中心'),
            'customer' => array('link' => '/customer/customer_list', 'name' => '客户中心'),
            'marketing' => array('link' => '/marketing/prom_goods_list', 'name' => '营销推广'),
            'wxmanager' => array('link' => '/wxmanager/wx_public_list', 'name' => '微信管理'),
            'count' => array('link' => '/count/index', 'name' => '统计报表'),
            'content' => array('link' => '/content/article_list', 'name' => '内容管理'),
            'system' => array('link' => '/admin/index', 'name' => '系统设置'),
            'support'=>array('link'=>'/support/apply_list','name'=>'售后中心'),
            'complaint'=>array('link'=>'/complaint/complaint_list','name'=>'投诉中心'),
            'districtadmin'=>array('link'=>'/districtadmin/record_sale','name'=>'专区管理'),
        );

        $safebox = Safebox::getInstance();
        $manager = $safebox->get('manager');
        if (isset($manager['roles']) && $manager['roles'] != 'administrator') {
            $roles = new Roles($manager['roles']);
            $result = $roles->getRoles();
            if (isset($result['rights']))
                $rights = $result['rights'];
            else
                $rights = '';
            if (is_array($nodes)) {
                $subMenuKey = array();
                foreach ($nodes as $key => $value) {
                    $_key = trim(strtr($key, '/', '@'), '@');
                    if (stripos($rights, $_key) === false)
                        unset($nodes[$key]);
                    else {
                        if (!isset($subMenuKey[$value['parent']]))
                            $subMenuKey[$value['parent']] = $key;
                        else {
                            if (stristr($key, '_list'))
                                $subMenuKey[$value['parent']] = $key;
                        }
                    }
                }
                $menuKey = array();
                foreach ($subMenu as $key => $value) {
                    if (isset($subMenuKey[$key])) {
                        $menuKey[$value['parent']] = $key;
                    } else
                        unset($subMenu[$key]);
                }
                foreach ($menu as $key => $value) {
                    if (!isset($menuKey[$key]))
                        unset($menu[$key]);
                    else {
                        $menu[$key]['link'] = $subMenuKey[$menuKey[$key]];
                    }
                }
            }
        }
        //var_dump($subMenuKey,$menuKey,$menu);exit;
        if (is_array($nodes))
            $this->nodes = $nodes;
        else
            $this->nodes = array();
        if (is_array($subMenu))
            $this->subMenu = $subMenu;
        else
            $this->subMenu = array();
        if (is_array($menu))
            $this->menu = $menu;
        else
            $this->menu = array();

        foreach ($this->nodes as $key => $nodes) {
            $this->_subMenu[$nodes['parent']][] = array('link' => $key, 'name' => $nodes['name'], 'hidden' => (isset($nodes['hidden']) && $nodes['hidden'] == true) ? true : false);
        }
        foreach ($this->subMenu as $key => $subMenu) {
            $this->_menu[$subMenu['parent']][] = array('link' => $key, 'name' => $subMenu['name']);
        }
        $this->link_key = '/' . (Req::get('con') == null ? strtolower(Tiny::app()->defaultController) : Req::get('con')) . '/' . (Req::get('act') == null ? Tiny::app()->getController()->defaultAction : Req::get('act'));
    }

    public function current_menu($key = null) {
        $key = $this->link_key;
        if (isset($this->nodes[$key])) {
            $subMenu = $this->nodes[$key]['parent'];
            $menu = $this->subMenu[$subMenu]['parent'];
            return array('menu' => $menu, 'subMenu' => $subMenu);
        }
        return null;
    }

    public function getMenu() {
        return isset($this->menu) ? $this->menu : array();
    }

    public function getSubMenu($key) {
        return isset($this->_menu[$key]) ? $this->_menu[$key] : array();
    }

    public function getNodes($key) {
        return isset($this->_subMenu[$key]) ? $this->_subMenu[$key] : array();
    }

    public function currentNode() {

        $key = $this->link_key;
        return isset($this->nodes[$key]) ? $this->nodes[$key] : array();
    }

    public function getLink() {
        return $this->link_key;
    }

}
