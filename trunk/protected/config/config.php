 <?php return array (
  'classes' => 
  array (
    0 => 'classes.*',
    1 => 'extensions.*',
    2 => 'classes.barcode.*',
    3 => 'classes.payments.*',
    4 => 'classes.delivery.*',
    5 => 'classes.oauth.*',
  ),
  'theme' => '_default',
  'urlFormat' => 'path',
  'db' => 
  array (
    'type' => 'mysqli',
    'tablePre' => 'tiny_',
    'host' => 'localhost',
    'user' => 'root',
    'password' => '',
    'name' => 'ymshop',
    'port' => '3306',
  ),
  'route' => 
  array (
    '/user/<_a:([a-z_]+)>' => 'ucenter/<_a>',
    '/mana' => 'admin/index',
    '/mana-<_a:([a-z_]+)>' => 'admin/<_a>',
    '/s/<_a:([a-z_]+)>' => 'simple/<_a>',
    '/<_a:([a-z0-9_]+)>.html' => 'index/<_a>',
    '/<_a:([a-z0-9_]+)>-<id:\\d+>.html' => 'index/<_a>',
    '/<_a:([a-z0-9_]+)>--<cid:\\d+>.html' => '<_c>/category',
    '/<_a:([a-z0-9_]+)>--<cid:\\d+>--<sort:\\d+>.html' => '<_c>/category',
    '/<_a:([a-z0-9_]+)>--<cid:\\d+>--<sort:\\d+>--<brand:\\d+>.html' => '<_c>/category',
    '/<_a:([a-z0-9_]+)>--<cid:\\d+>--<sort:\\d+>--<price:([0-9-]+)>--<brand:\\d+>.html' => '<_c>/category',
    '/<_a:([a-z0-9_]+)>--<cid:\\d+>--<sort:\\d+>--<brand:\\d+>--<price:([0-9-]+)>.html' => '<_c>/category',
    '/<_a:([a-z0-9_]+)>--<cid:\\d+>--<sort:\\d+>--<price:([0-9-]+)>.html' => '<_c>/category',
    '/<_a:([a-z0-9_]+)>--<cid:\\d+>--<sort:\\d+>--<\\d+:\\d+>.html' => '<_c>/category',
    '/<_c:([a-z0-9_]+)>--<_a:([a-z0-9_]+)>--<id:\\d+>.html' => '<_c>/category',
    '/category/<cid:\\d+>--<sort:\\d+>.html' => '<_c>/category',
  ),
  'extConfig' => 
  array (
    'controllerExtension' => 
    array (
      0 => 'ControllerExt',
    ),
  ),
  'themes_mobile' => 'mobile',
);?>