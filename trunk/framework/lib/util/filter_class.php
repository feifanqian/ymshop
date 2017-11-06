<?php

/**
 * 关于信息过滤的文件
 *
 * @class Filter
 */
final class Filter {
    /*
     * @param $str 字符串
     * @return 返回整形数据
     * @note 实现输入的数据转换为整型
     */

    public static function int($str) {
        return intval($str);
    }

    /**
     * 浮点过滤
     *
     * @access public
     * @param mixed $str
     * @return mixed
     */
    public static function float($str) {
        return floatval($str);
    }

    /**
     * @param $str 字符串
     * @return 字符串
     * @note 实现简单文本的过滤
     */
    public static function str($str) {
        $str = self::sql($str);
        $tran_before = array("<", ">");
        $tran_after = array("&lt;", "&gt;");
        return str_replace($tran_before, $tran_after, $str);
    }

    /*     * @param $str 常见字符
     * @return 字符串
     * @note 处理掉特殊字符
     */

    public static function commonChar($str) {
        $regex = "/\/|\~|\!|\@|\#|\\$|\%|\^|\&|\*|\(|\)|\_{2,}|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\.|\/|\;|\'|\"|\-{2,}|\=|\\\|\|/";
        return preg_replace($regex, "", $str);
    }

    /*
     * @param $str 字符串
     * @return 字符串
     * @note 处理HTML编辑器的内容，主要是解决JavaScript的注入问题
     */

    public static function text($str) {
        if (get_magic_quotes_gpc()) {
            $str = stripslashes($str);
            $str = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
            $str = addslashes($str);
        } else {
            $str = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
        }
        return self::sql($str);
    }

    /**
     * 清除所有标签
     * @param  string $str 要处理的字符串
     * @return string      处理后的结果
     */
    public static function txt($str) {
        $str = preg_replace('/<[^>]*>/i', '', $str);
        $str = self::str($str);
        return $str;
    }

    public static function sql($str) {
        if (!get_magic_quotes_gpc()) {
            //不使用主要是因为，先有mysql的连接
            //$str =  mysql_real_escape_string($str);
            $str = addslashes($str);
        }
        $str = preg_replace('/([^a-z]+)(select|insert|update|delete|union|into|load_file|outfile|and|or|sleep|tiny_)/i', '&#160;$2', $str);
        return $str;
    }

    /**
     * 处理各种内容的输入,默认是sql的方式过滤
     */
    public static function inputFilter($content, $type = 'sql') {
        if (is_string($content)) {
            return self::$type($content);
        } elseif (is_array($content)) {
            foreach ($content as $key => $val) {
                $content[$key] = self::inputFilter($val, $type);
            }
            return $content;
        } elseif (is_object($content)) {
            $vars = get_object_vars($content);
            foreach ($vars as $key => $val) {
                $content->$key = self::inputFilter($val, $type);
            }
            return $content;
        } else {
            return $content;
        }
    }

    /**
     * 过滤表单,然后重新写回表单
     * @param  array  $rule 表单各字段验证的标尺规则
     */
    public static function form($rule = array()) {
        if (empty($rule)) {
            $args = Req::args();
            foreach ($args as $key => $value) {
                Req::args($key, self::sql($value));
            }
        } else {
            foreach ($rule as $key => $re) {
                $key = strtolower($key);
                if (strpos($re, '|')) {
                    $res = explode('|', $re);
                    if (method_exists('Filter', $key))
                        foreach ($res as $re)
                            Req::args($re, self::inputFilter(Req::args($re), $key));
                }
                if (method_exists('Filter', $key))
                    Req::args($re, self::inputFilter(Req::args($re), $key));
            }
        }
    }

}
