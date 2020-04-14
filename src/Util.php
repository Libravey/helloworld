<?php

/**
 * Created by PhpStorm.
 * User: lsq
 * Date: 14-5-22
 * Time: 下午3:54
 */
class Util
{
    public static $item_json = null;
    public static $vip_json = null;
    public static $server_config = null;

    public static $platform_json = null; //平台
    public static $agent_json = null; //代理

    public static $mongo_con = null;
    public static $mysql_con = null;

    public static function getJson($file)
    {
        if (!file_exists($file)) {
            return;
        }

        $content = file_get_contents($file);
        $pattern = "/\/\*(\s|\S)*?\*\/|\/\/.*?(\r\n|\n|\r)/"; //过滤多行和单行注释
        $content = preg_replace($pattern, "", $content);
        $getJson = json_decode($content, true);

        return $getJson;
    }

    public static function getSceneJson($config)
    {
        $file = ROOT . '/../../../server/config/' . $config;
        if (!file_exists($file)) {
            return;
        }
        $content = file_get_contents($file);
        if (strpos($content, "\xEF\xBB\xBF") === 0) { //去除bom头
            $content = substr($content, 3);
        }
        $pattern = "/\/\*(\s|\S)*?\*\/|\/\/.*?(\r\n|\n|\r)/"; //过滤多行和单行注释
        $content = preg_replace($pattern, "", $content);

        $getJson = json_decode($content, true);
        return $getJson;
    }

    public static function getJsonConfig($config)
    {
        return self::getJson(ROOT . '/../../../server/config/' . $config);
    }

    public static function getItem($id)
    {
        empty(self::$item_json) and self::$item_json = self::getJson(ROOT . '/../../../server/config/item/item.json');

        return isset(self::$item_json[$id]) ? self::$item_json[$id] : [];
    }

    public static function getItemName($id)
    {
        $item = self::getItem($id);

        return $item ? $item['name'] : $id;
    }

    public static function getVipName($type)
    {
        empty(self::$vip_json) and self::$vip_json = self::getJson(ROOT . '/../../../server/config/role/vip.json');

        return isset(self::$vip_json[$type]) ? self::$vip_json[$type]['name'] : '无';
    }

    public static function platformIdToFlag($id)
    {
        if ($id == 0) {
            return 0;
        }

        self::$platform_json or self::$platform_json = self::getJson(ROOT . '/../../../server/config/tiny/platform.json');
        $flag = $id;
        foreach (self::$platform_json as $k => $v) {
            if ($id == $v) {
                $flag = $k;
                break;
            }
        }

        return $flag;
    }

    public static function channelIdToFlag($id)
    {
        if ($id == 0) {
            return 0;
        }

        self::$agent_json or self::$agent_json = self::getJson(ROOT . '/../../../server/config/tiny/channel.json');
        $flag = $id;
        foreach (self::$agent_json as $k => $v) {
            if ($id == $v) {
                $flag = $k;
                break;
            }
        }

        return $flag;
    }

    /**
     * [marketToChannel 根据包号查询所属渠道]
     * @param  [type] $flag [包号]
     * @return [type]       [返回渠道名]
     */
    public static function marketToChannel($flag)
    {
        $mongo = self::getMongoCon();
        $row = $mongo->backstage->channel_package->findOne(['id' => 1]);
        $channelPackage = $row['cp'];
        $channel = '';
        foreach ($channelPackage as $channelFlag => $packages) {
            if (in_array($flag, $packages)) {
                $channel = self::channelIdToFlag($channelFlag);
                continue;
            }
        }
        return $channel;
    }

    public static function platformFlagToId($flag)
    {
        self::$platform_json or self::$platform_json = self::getJson(ROOT . '/../../../server/config/tiny/platform.json');

        return isset(self::$platform_json[$flag]) ? self::$platform_json[$flag] : -1;
    }

    public static function channelFlagToId($flag)
    {
        self::$agent_json or self::$agent_json = self::getJson(ROOT . '/../../../server/config/tiny/channel.json');

        return isset(self::$agent_json[$flag]) ? self::$agent_json[$flag] : -1;
    }

    public static function logDir()
    {
        $logDir = ROOT . '/../../../log/';
        if (!is_writable($logDir)) {
            $logDir = ROOT . '/runtime/';
        }

        return $logDir;
    }

    public static function log($msg, $category = 'info')
    {
        $debugInfo = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        $file = $debugInfo[0]['file'];
        $line = $debugInfo[0]['line'];

        if (is_array($msg) || is_object($msg)) {
            $msg = print_r($msg, true);
        } else if ($msg === null) {
            $msg = 'NULL';
        } else if ($msg === true) {
            $msg = 'true';
        } else if ($msg === false) {
            $msg = 'false';
        }

        $msg = "[" . date('Y-m-d H:i:s') . "] file $file line $line:\n$msg\n";

        $logfile = self::logDir() . "$category.txt";
        @file_put_contents($logfile, $msg, FILE_APPEND);
    }

    public static function humanTime($seconds)
    {
        if ($seconds <= 0) {
            return '-';
        }
        $str = '';

        $split = array(60 * 60 * 24, 60 * 60, 60);
        $names = array('天', '时', '分');
        foreach ($split as $k => $v) {
            if ($seconds >= $v) {
                $tmp_num = floor($seconds / $v);
                $str .= $tmp_num . $names[$k];
                $seconds -= $tmp_num * $v;
            }
        }
        empty($str) && $str = "{$seconds}秒";

        return $str;
    }

    public static function getConfig($name = null)
    {
        self::$server_config or self::$server_config = @include ROOT . "/../config/single.php";

        if ($name) {
            return isset(self::$server_config[$name]) ? self::$server_config[$name] : null;
        }

        if (!is_array(self::$server_config)) {
            return [];
        }

        return self::$server_config;
    }

    public static function parseList($list)
    {
        $str = trim($list);
        $str = str_replace(';', ',', $str);
        $str = str_replace('；', ',', $str);
        $str = str_replace('，', ',', $str);
        $str = str_replace(',', "\n", $str);

        $arr = explode("\n", $str);

        $data = array();
        //防止在windows系统下数组中的元素出现\r的情况
        foreach ($arr as $tmp) {
            trim($tmp) and $data[] = trim($tmp);
        }

        return array_unique($data);
    }

    public static function getMongoCon()
    {
        if (!self::$mongo_con instanceof MongoClient) {
            $config = self::getConfig();
            self::$mongo_con = new MongoClient("mongodb://" . $config['mongo_host'] . ':' . $config['mongo_port']);
        }

        return self::$mongo_con;
    }

    public static function getMysqlCon()
    {
        if (!self::$mysql_con instanceof mysqli) {
            $config = Util::getConfig();
            self::$mysql_con = new mysqli($config['mysql_host'], $config['mysql_user'], $config['mysql_passwd'], $config['db_name'], $config['mysql_port']);
        }

        return self::$mysql_con;
    }

    public static function exportExcel(array $data, $filename, $attach = true)
    {
        if (!$data) {
            return;
        }

        $objPHPExcel = new PHPExcel();

        if (!is_array(current(current($data)))) {
            $worksheet = $objPHPExcel->getSheet();
            $row_pos = 1;
            foreach ($data as $row) {
                $col_pos = 0;
                foreach ($row as $cell) {
                    $worksheet->setCellValueByColumnAndRow($col_pos, $row_pos, $cell);
                    $col_pos++;
                }
                $row_pos++;
            }
        } else {
            $objPHPExcel->removeSheetByIndex(0);
            foreach ($data as $title => $content) {
                $title === '' and $title = 'empty';
                $worksheet = new PHPExcel_Worksheet($objPHPExcel, (string)$title);
                $objPHPExcel->addSheet($worksheet);
                $row_pos = 1;
                foreach ($content as $row) {
                    $col_pos = 0;
                    foreach ($row as $cell) {
                        $worksheet->setCellValueByColumnAndRow($col_pos, $row_pos, $cell);
                        $col_pos++;
                    }
                    $row_pos++;
                }
            }
        }

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        if (!$attach) {
            $objWriter->save($filename . '.xlsx');
        } else {
            $filename = iconv('UTF-8', 'GBK', $filename);
            header('Content-Type: application/vnd.ms-excel');
            header("Content-Disposition: attachment;filename={$filename}.xlsx");
            header('Cache-Control: max-age=0');
            $objWriter->save('php://output');
        }

        $objPHPExcel->disconnectWorksheets();
        unset($objPHPExcel);
    }

    //调用单服的方法
    public static function remoteCall($url, $class_method, array $params = [])
    {
        if (!$url) {
            throw new Exception('$url must not be empty');
        }

        $postData = [
            'r' => 'call_method',
            'class_method' => $class_method,
            'params' => json_encode($params),
        ];
        self::sign($postData);
        $rc = new RollingCurl();
        $rc->post($url, $postData);

        return $rc->execute();
    }

    public static function sign(array &$data)
    {
        $data['unixtime'] = time();
        $data['sign'] = md5("f21468d133db7aec1d02b222d6c613c6" . $data['unixtime']);
    }

    public static function flag()
    {
        $config = require ROOT . "/config/single.php";

        return $config['flag'];
    }
}
