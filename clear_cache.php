<?php
// 清除OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache已清除\n";
} else {
    echo "OPcache不可用\n";
}
?>
