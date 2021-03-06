<?php
class HtreportModel {
    //缓存用户的uid
    private static $uid = [];
    public static $startTime = '';
    public static $endTime = '';
    public function __construct($data, $startTime, $endTime) {
        self::$uid = $data;
        self::$startTime = $startTime;
        self::$endTime = $endTime;
    }
    //用户的分享记录
    public function shareRecord() {
        return M()->query("SELECT count(uid) share, report_date FROM ngw_share_log WHERE uid IN(".(self::$uid).") AND report_date BETWEEN '".(self::$startTime)."' AND '".(self::$endTime)."' GROUP BY report_date", 'all');
    }
    //用户的分享率 (去重用户分享数/去重用户登录数)
    public function shareRate() {
        return M()->query("SELECT round(a. share / b.num * 100 , 2) s_percent, a.report_date FROM(SELECT count(DISTINCT uid) share , report_date FROM ngw_share_log WHERE uid IN(".(self::$uid).") AND report_date BETWEEN '".(self::$startTime)."' AND '".(self::$endTime)."' GROUP BY report_date) a JOIN(SELECT count(DISTINCT uid) num , report_date FROM ngw_uid_login_log WHERE uid IN(".(self::$uid).") AND report_date BETWEEN '".(self::$startTime)."' AND '".(self::$endTime)."' GROUP BY report_date) b ON b.report_date = a.report_date", 'all');
    }
    //用户新增 (成功注册且有过淘宝授权的)
    public function channelsNewlyAdded() {
        return M()->query("SELECT count(DISTINCT uid) track_num ,report_date FROM ngw_taobao_log WHERE uid IN(".(self::$uid).") AND report_date BETWEEN '".(self::$startTime)."' AND '".(self::$endTime)."' GROUP BY report_date", 'all');
    }
    //邀请新增 (由渠道用户邀请过来的好友 成功注册且有过淘宝授权的)
    public function inviteNewlyAdded() {
        return M()->query("SELECT count(a.objectId) invatation_num ,report_date FROM(SELECT objectId FROM ngw_uid WHERE sfuid IN(".(self::$uid).") AND report_date BETWEEN '".(self::$startTime)."' AND '".(self::$endTime)."') a JOIN ngw_taobao_log b ON b.uid = a.objectId GROUP BY report_date", 'all');
    }
    //有效用户 (有过淘宝授权的有过点击记录的)
    public function validUser() {
        return M()->query("SELECT report_date , count(DISTINCT uid) click_num FROM ngw_click_log WHERE uid IN(".(self::$uid).") AND uid IN(SELECT uid FROM ngw_taobao_log) AND report_date BETWEEN '".(self::$startTime)."' AND '".(self::$endTime)."' GROUP BY report_date", 'all');
    }
    //留存用户数 (除掉今天新增的用户 有过登录记录的 则为留存用户)
    public function leaveBehindUser() {
        return M()->query("SELECT a.num - IFNULL(b.number, 0) keep,a.report_date FROM( SELECT count(DISTINCT uid) num , report_date FROM ngw_uid_login_log WHERE uid IN(".(self::$uid).") AND report_date BETWEEN '".(self::$startTime)."' AND '".(self::$endTime)."' GROUP BY report_date) a LEFT JOIN( SELECT count(DISTINCT uid) number , report_date FROM ngw_tracking WHERE uid IS NOT NULL GROUP BY report_date) b ON b.report_date = a.report_date", 'all');
    }
    //统计用户的下单额(刨除掉退单的)
    public function userPurchaseNumber() {
        return M()->query("SELECT sum(fee) fee, report_date FROM ngw_shopping_log WHERE uid IN(".(self::$uid).") AND order_id NOT IN(SELECT order_id FROM ngw_shopping_log WHERE order_status = 5) AND order_status = 2 AND report_date BETWEEN '".(self::$startTime)."' AND '".(self::$endTime)."' GROUP BY report_date", 'all');
    }
    //由用户给我们带来的净收入 (刨除掉退单的)
    public function userProfits() {
        $data = M()->query("SELECT a.uid,b.sfuid,a.deal_price,a.rating,a.source,a.created_date FROM (select * from ngw_order WHERE deal_price IS NOT NULL AND order_id NOT IN(SELECT order_id FROM ngw_order_status WHERE order_status = 5) AND uid in(".(self::$uid).") AND created_date BETWEEN '".(self::$startTime)."' AND '".(self::$endTime)."') a LEFT JOIN ngw_uid b ON b.objectId = a.uid", 'all');
        foreach($data as &$v) {
            //先计算出该商品带来的总利润
            $v['benifit'] = $v['rating'] / 100 * $v['deal_price'];
            // 只要有师傅的 都先减掉师傅的20%
            if($v['sfuid'])
                $v['benifit'] = $v['benifit'] - $v['rating'] / 100 * $v['deal_price'] * 0.2;
            //选品库 以及 淘宝api搜到的商品 都返利给用户 70%
           if($v['source'] == 1 || $v['source'] == 10) {
                $v['benifit'] = $v['benifit'] - $v['rating'] / 100 * $v['deal_price'] * 0.7;
            }
        }
        $res = [];
        foreach($data as $val) {
            if(isset($res[$val['created_date']])) {
                $res[$val['created_date']]['benifit'] = $val['benifit'] + $res[$val['created_date']]['benifit'];
            } else {
                $res[$val['created_date']]['report_date'] = $val['created_date'];
                $res[$val['created_date']]['benifit'] = $val['benifit'];
            }
            $res[$val['created_date']]['benifit'] = round($res[$val['created_date']]['benifit'], 2);
        }
        return array_values($res);
    }
    //统计用户的好友的下单额(刨除掉退单的)
    public function userFriendPurchaseNumber() {
        return M()->query("SELECT sum(fee) td_fee, report_date FROM ngw_shopping_log WHERE uid IN(select objectId from ngw_uid where sfuid in(".(self::$uid).")) AND order_id NOT IN(SELECT order_id FROM ngw_shopping_log WHERE order_status = 5) AND order_status = 2 AND report_date BETWEEN '".(self::$startTime)."' AND '".(self::$endTime)."' GROUP BY report_date", 'all');
    }
    //由用户的好友带来的毛利润 (刨除掉退单的)
    public function userFriendProfits() {
        M()->query("SELECT sum(benifit) td_benifit , report_date FROM ngw_shopping_log WHERE uid IN(SELECT objectId FROM ngw_uid WHERE sfuid IN(".(self::$uid).")) AND order_id NOT IN( SELECT order_id FROM ngw_shopping_log WHERE order_status = 5) AND report_date BETWEEN '".(self::$startTime)."' AND '".(self::$endTime)."' GROUP BY report_date", 'all');
    }
    //由用户给我们带来的毛利润(刨除掉退单的)
    public function userMaoProfits() {
        return M()->query("SELECT sum(benifit) benifit , report_date FROM ngw_shopping_log WHERE uid IN( SELECT objectId FROM ngw_uid WHERE sfuid IN(".(self::$uid).")) AND order_id NOT IN( SELECT order_id FROM ngw_shopping_log WHERE order_status = 5) AND report_date BETWEEN '".(self::$startTime)."' AND '".(self::$endTime)."' GROUP BY report_date", 'all');
    }
    //拿订单号计算出该商品我们获得的净利润
    public function netProfit($orderId = ['18182021205113222', '16830043335093706']) {

    }
}
