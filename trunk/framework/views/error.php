<div style="border:1px solid #d0d0d0;padding-left:20px;margin:0 0 10px 0;background:#f9f9f9;font-size:14px;">

    <h4><font color="#0066ff">发生了一个已知的错误信息</font></h4>
    <?php if (DEBUG) { ?>
        <p>级别: <?php echo $errorType; ?></p>
        <p>代码: <?php echo $codes; ?></p>
        <p>内容:  <?php echo $message; ?></p>
        <p>文件: <?php echo $file; ?></p>
        <p>行号: <?php echo $line; ?></p>
        <p>堆栈: 
        <table class='error' >
            <tr class='head'><td>#</td><td>Object</td><td>Function</td><td>File</td></tr>
            <?php foreach ($errorStack as $item) { ?>
                <tr><td><?php echo $item['num']; ?></td><td><?php echo $item['object']; ?></td><td><?php echo $item['function']; ?></td><td><?php echo $item['file']; ?>:(<?php echo $item['line']; ?>)</td></tr>
            <?php } ?>
        </table>
    <?php } ?>
</p>
</div>
