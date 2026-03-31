<?php if (!defined('ABSPATH')) exit; ?>
<div class="sg-overlay">
  <div class="sg-overlay-card">
    <h1 class="sg-title">Welcome to ScrinGrab</h1>
    <p class="sg-subtitle">Enter the dashboard to start capturing and backing up your pages.</p>

    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
      <?php wp_nonce_field('sg_mock_login'); ?>
      <input type="hidden" name="action" value="sg_mock_login">
      <button type="submit" class="button button-primary sg-login-btn">Continue</button>
    </form>
  </div>
</div>

