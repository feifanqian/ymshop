<?php

/**

 * 通用的树型类

 * @author XiaoYao <476552238li@gmail.com>

 */
class Tree {

    protected static $_instance = null;
    public $options = array();

    /**

     * 单例方法

     * @return Tree

     */
    public static function getInstance() {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**

     * 生成树型结构所需要的2维数组

     * @var array

     */
    public $arr = array();

    /**

     * 生成树型结构所需修饰符号，可以换成图片

     * @var array

     */
    public $icon = array('│', '├', '└');
    public $nbsp = "&nbsp;";
    public $pidname = 'parentid';

    function __construct() {
        
    }

    /**

     * 初始化方法

     * @param array 2维数组，例如：

     * array(

     *      1 => array('id'=>'1','parentid'=>0,'name'=>'一级栏目一'),

     *      2 => array('id'=>'2','parentid'=>0,'name'=>'一级栏目二'),

     *      3 => array('id'=>'3','parentid'=>1,'name'=>'二级栏目一'),

     *      4 => array('id'=>'4','parentid'=>1,'name'=>'二级栏目二'),

     *      5 => array('id'=>'5','parentid'=>2,'name'=>'二级栏目三'),

     *      6 => array('id'=>'6','parentid'=>3,'name'=>'三级栏目一'),

     *      7 => array('id'=>'7','parentid'=>3,'name'=>'三级栏目二')

     *      )

     */
    public function init($arr = array(), $pidname = 'parentid') {
        $this->arr = $arr;
        $this->pidname = $pidname;
        return $this;
    }

    /**

     * 得到子级数组

     * @param int

     * @return array

     */
    public function get_child($myid) {
        $newarr = array();
        if (is_array($this->arr)) {
            foreach ($this->arr as $value) {
                if (!isset($value['id']))
                    continue;
                if ($value[$this->pidname] == $myid)
                    $newarr[$value['id']] = $value;
            }
        }
        return $newarr;
    }

    /**

     * 得到当前位置所有父辈数组

     * @param int 

     * @return array

     */
    public function get_pos($myid) {
        $pid = 0;
        $newarr = array();
        foreach ($this->arr as $value) {
            if (!isset($value['id']))
                continue;
            if ($value['id'] == $myid) {
                $newarr[] = $value;
                $pid = $value[$this->pidname];
            }
        }
        if ($pid) {
            $arr = $this->get_pos($pid);
            $newarr = array_merge($arr, $newarr);
        }
        return $newarr;
    }

    /**

     * 读取指定节点的所有孩子节点

     * @param int $myid

     * @return array

     */
    public function get_children($myid) {
        $newarr = array();
        if (is_array($this->arr)) {
            foreach ($this->arr as $value) {
                if (!isset($value['id']))
                    continue;
                if ($value[$this->pidname] == $myid) {
                    $newarr[$value['id']] = $value;
                    $newarr = array_merge($newarr, $this->get_children($value['id']));
                }
            }
        }
        return $newarr;
    }

    /**

     * 得到树型结构

     * @param int $myid 表示获得这个ID下的所有子级

     * @param string $itemtpl 条目模板 如："<option value=@id @selected>@spacer@name</option>"

     * @param mixed $selectids 被选中的ID，比如在做树型下拉框的时候需要用到

     * @param string $itemprefix 每一项前缀

     * @param string $toptpl 顶级栏目的模板

     * @return string

     */
    public function get_tree($myid, $itemtpl, $selectids = 0, $itemprefix = '', $toptpl = '') {
        $ret = '';
        $number = 1;
        $childs = $this->get_child($myid);
        if ($childs) {
            $total = count($childs);
            foreach ($childs as $value) {
                $id = $value['id'];
                $j = $k = '';
                if ($number == $total) {
                    $j .= $this->icon[2];
                    $k = $itemprefix ? $this->nbsp : '';
                } else {
                    $j .= $this->icon[1];
                    $k = $itemprefix ? $this->icon[0] : '';
                }
                $spacer = $itemprefix ? $itemprefix . $j : '';
                $selected = in_array($id, (is_array($selectids) ? $selectids : explode(',', $selectids))) ? 'selected' : '';
                $value = array_merge($value, array('selected' => $selected, 'spacer' => $spacer));
                $value = array_combine(array_map(function($k) {
                            return '@' . $k;
                        }, array_keys($value)), $value);
                $nstr = strtr((($value["@{$this->pidname}"] == 0 || $this->get_child($id) ) && $toptpl ? $toptpl : $itemtpl), $value);
                $ret .= $nstr;
                $ret .= $this->get_tree($id, $itemtpl, $selectids, $itemprefix . $k . $this->nbsp, $toptpl);
                $number++;
            }
        }
        return $ret;
    }

    /**

     * 得到树型结构

     * @param int $myid 表示获得这个ID下的所有子级

     * @param string $itemtpl 条目模板 如："<li value=@id @selected>@spacer@name @childlist</li>"

     * @param string $selectids 选中的ID

     * @param string $wraptag 子列表包裹标签

     * @return string

     */
    public function get_tree_ul($myid, $itemtpl, $selectids = '', $wraptag = 'ul', $wrapattr = '', $itemprefix = '') {
        $str = '';
        $number = 1;
        $childs = $this->get_child($myid);
        if ($childs) {
            $total = count($childs);
            foreach ($childs as $value) {
                $id = $value['id'];
                $j = $k = '';
                if ($number == $total) {
                    $j .= $this->icon[2];
                    $k = $itemprefix ? $this->nbsp : '';
                } else {
                    $j .= $this->icon[1];
                    $k = $itemprefix ? $this->icon[0] : '';
                }
                $spacer = $itemprefix ? $itemprefix . $j : '';
                unset($value['child']);
                $selected = in_array($id, (is_array($selectids) ? $selectids : explode(',', $selectids))) ? 'selected' : '';
                $value = array_merge($value, array('selected' => $selected, 'spacer' => $spacer));
                $value = array_combine(array_map(function($k) {
                            return '@' . $k;
                        }, array_keys($value)), $value);
                $nstr = strtr($itemtpl, $value);
                $childdata = $this->get_tree_ul($id, $itemtpl, $selectids, $wraptag, $wrapattr, $itemprefix);
                $childlist = $childdata ? "<{$wraptag} {$wrapattr}>" . $childdata . "</{$wraptag}>" : "";
                $str .= strtr($nstr, array('@childlist' => $childlist));
            }
        }
        return $str;
    }

    public function get_tree_menu($myid, $itemtpl, $selectids = '', $wraptag = 'ul', $wrapattr = '', $deeplevel = 0) {
        $str = '';
        $childs = $this->get_child($myid);
        if ($childs) {
            foreach ($childs as $value) {
                $id = $value['id'];
                unset($value['child']);
                $selected = in_array($id, (is_array($selectids) ? $selectids : explode(',', $selectids))) ? 'selected' : '';
                $value = array_merge($value, array('selected' => $selected));
                $value = array_combine(array_map(function($k) {
                            return '@' . $k;
                        }, array_keys($value)), $value);
                $nstr = strtr($itemtpl, $value);
                $childdata = $this->get_tree_menu($id, $itemtpl, $selectids, $wraptag, $wrapattr, $deeplevel + 1);
                $childlist = $childdata ? "<{$wraptag} {$wrapattr}>" . $childdata . "</{$wraptag}>" : "";
                $childlist = strtr($childlist, array('@class' => $childdata ? 'last' : ''));
                $value = array(
                    '@childlist' => $childlist,
                    '@url' => $childdata ? "javascript:;" : "/{$value['@module']}/{$value['@controller']}/" . str_replace('-', '', $value['@action']),
                    '@badge' => ($childdata ? '<i class="fa fa-angle-left pull-right"></i>' : ''),
                    '@class' => ($selected ? ' active' : '') . ($childdata ? ' treeview' : ''),
                );
                $str .= strtr($nstr, $value);
            }
        }
        return $str;
    }

    /**
     * @param integer $myid 要查询的ID
     * @param string $itemtpl1   第一种HTML代码方式
     * @param string $itemtpl2  第二种HTML代码方式
     * @param mixed $selectids  默认选中
     * @param integer $itemprefix 前缀
     */
    public function get_tree_category($myid, $itemtpl1, $itemtpl2, $selectids = 0, $itemprefix = '') {
        $ret = '';
        $number = 1;
        $childs = $this->get_child($myid);
        if ($childs) {
            $total = count($childs);
            foreach ($childs as $id => $value) {
                $j = $k = '';
                if ($number == $total) {
                    $j .= $this->icon[2];
                    $k = $itemprefix ? $this->nbsp : '';
                } else {
                    $j .= $this->icon[1];
                    $k = $itemprefix ? $this->icon[0] : '';
                }
                $spacer = $itemprefix ? $itemprefix . $j : '';
                $selected = in_array($id, (is_array($selectids) ? $selectids : explode(',', $selectids))) ? 'selected' : '';
                $value = array_merge($value, array('selected' => $selected, 'spacer' => $spacer));
                $value = array_combine(array_map(function($k) {
                            return '@' . $k;
                        }, array_keys($value)), $value);
                $nstr = strtr(!isset($value['@disabled']) || !$value['@disabled'] ? $itemtpl1 : $itemtpl2, $value);

                $ret .= $nstr;
                $ret .= $this->get_tree_category($id, $itemtpl1, $itemtpl2, $selectids, $itemprefix . $k . $this->nbsp);
                $number++;
            }
        }
        return $ret;
    }

    /**
     * 获取栏目树状数组
     * @param string $myid 要查询的ID
     * @param string $nametpl 名称条目模板
     * @param string $itemprefix 前缀
     * @return string
     */
    public function get_tree_array($myid, $nametpl = '', $itemprefix = '') {
        $childs = $this->get_child($myid);
        $n = 0;
        $data = array();
        $number = 1;
        if ($childs) {
            $total = count($childs);
            foreach ($childs as $id => $value) {
                $j = $k = '';
                if ($number == $total) {
                    $j .= $this->icon[2];
                    $k = $itemprefix ? $this->nbsp : '';
                } else {
                    $j .= $this->icon[1];
                    $k = $itemprefix ? $this->icon[0] : '';
                }
                $spacer = $itemprefix ? $itemprefix . $j : '';
                $value['spacer'] = $spacer;
                $data[$n] = $value;
                $data[$n]['childlist'] = array();
                if ($this->get_child($id)) {
                    $data[$n]['childlist'] = $this->get_tree_array($id, $nametpl, $itemprefix . $k . $this->nbsp);
                } else {
                    if ($nametpl) {
                        $value = array_combine(array_map(function($k) {
                                    return '@' . $k;
                                }, array_keys($value)), $value);
                        $data[$n]['name'] = strtr($nametpl, $value);
                    }
                }
                $n++;
                $number++;
            }
        }
        return $data;
    }

    public function get_tree_list($data = array()) {
        $arr = array();
        foreach ($data as $k => $v) {

            $childlist = isset($v['childlist']) ? $v['childlist'] : array();
            unset($v['childlist']);
            $v['name'] = $v['spacer'] . ' ' . $v['name'];
            if ($v['id'])
                $arr[] = $v;
            if ($childlist) {
                $arr = array_merge($arr, $this->get_tree_list($childlist));
            }
        }
        return $arr;
    }

    public function get_tree_view($myid, $nametpl = '') {
        $childs = $this->get_child($myid);
        $n = 0;
        $data = array();
        $allowkey = array_flip(array('id', 'pid', 'text', 'icon', 'selectedIcon', 'color', 'backColor', 'href', 'selectable', 'state', 'tags', 'nodes'));
        if ($childs) {
            foreach ($childs as $id => $value) {
                $value['text'] = isset($value['text']) ? $value['text'] : $value['name'];
                $value = array_intersect_key($value, $allowkey);
                if (isset($value['state']))
                    $value['state'] = explode(',', $value['state']);
                if (isset($value['tags']))
                    $value['tags'] = explode(',', $value['tags']);
                if ($nametpl) {
                    $value = array_combine(array_map(function($k) {
                                return '@' . $k;
                            }, array_keys($value)), $value);

                    $value['text'] = strtr($nametpl, $value);
                }
                $data[$n] = $value;
                if ($this->get_child($id)) {
                    $data[$n]['children'] = array();
                    $data[$n]['children'] = $this->get_tree_view($id);
                }
                $n++;
            }
        }
        return $data;
    }

}