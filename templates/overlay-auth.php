<?php if (!defined('ABSPATH')) exit; ?>
<div class="sg-overlay">
  <div class="sg-overlay-card">
    <h1 class="sg-title">Welcome to ScrinGrab</h1>
    <p class="sg-subtitle">Sign in to start capturing and backing up your pages with ScrinGrab.</p>

    <a class="button button-primary sg-login-btn"
       href="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?page=scripgrab&sg_action=oauth_start'), 'sg_oauth_start') ); ?>">
      Continue with Google
    </a>

    <p class="sg-later-note">You can connect real Google OAuth later.</p>
  </div>
</div>
