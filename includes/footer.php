</div><!-- /.main-content -->

<script src="<?php echo rtrim(BASE_URL, '/\\'); ?>/assets/lib/sweetalert2/sweetalert2.all.min.js"></script>
<script>
    // Global config available to all scripts
    window.TM = {
        baseUrl: '<?php echo rtrim(BASE_URL, "/\\"); ?>',
        userId: '<?php echo $isLoggedIn ? $currentUser['id'] : ''; ?>',
        isAdmin: <?php echo $isAdminUser ? 'true' : 'false'; ?>,
        userName: '<?php echo $isLoggedIn ? htmlspecialchars($currentUser['first_name'], ENT_QUOTES) : ''; ?>',
        pollInterval: <?php echo defined('NOTIFICATION_POLL_INTERVAL') ? NOTIFICATION_POLL_INTERVAL : 7000; ?>,
    };
</script>
<script src="<?php echo rtrim(BASE_URL, '/\\'); ?>/assets/js/app.js"></script>
<script src="<?php echo rtrim(BASE_URL, '/\\'); ?>/assets/js/dob-picker.js"></script>
<?php if (isset($extraJs)): ?>
    <?php foreach ((array)$extraJs as $js): ?>
        <script src="<?php echo rtrim(BASE_URL, '/\\'); ?>/assets/js/<?php echo $js; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
