<?php
if (!defined('ABSPATH')) exit;
$user = ScripGrab\Auth::current_user();
?>
<div class="sg-profile">
  <img src="<?php echo esc_url($user['picture']); ?>" class="sg-profile-avatar" alt="">
  <div class="sg-profile-meta">
    <span class="sg-profile-name"><?php echo esc_html($user['name']); ?></span>
    <span class="sg-profile-email"><?php echo esc_html($user['email']); ?></span>
  </div>
  <div class="sg-profile-actions">
    <a href="<?php echo esc_url(wp_nonce_url( admin_url('admin.php?page=scripgrab&sg_action=logout'), 'sg_auth_action' )); ?>">Logout</a> •
    <a href="<?php echo esc_url(wp_nonce_url( admin_url('admin.php?page=scripgrab&sg_action=switch'), 'sg_auth_action' )); ?>">Switch</a>
  </div>
</div>

