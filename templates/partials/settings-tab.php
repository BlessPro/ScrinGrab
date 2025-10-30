<?php if (!defined('ABSPATH')) exit; ?>
<div class="sg-panel">
  <div class="sg-settings-left">
    <h2>Backup Settings</h2>
    <p class="sg-small">Configure how many copies to keep and how often to capture.</p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('sg_save_settings'); ?>
      <input type="hidden" name="action" value="sg_save_settings">

      <p><strong>Retention</strong></p>
      <select name="retention">
        <option value="1">Keep 1 copy</option>
        <option value="2">Keep 2 copies</option>
        <option value="3" selected>Keep 3 copies</option>
        <option value="4">Keep 4 copies</option>
      </select>

      <p style="margin-top:1rem;"><strong>Frequency</strong></p>
      <select name="frequency">
        <option value="manual">Manual</option>
        <option value="daily">Daily</option>
        <option value="weekly" selected>Weekly</option>
        <option value="monthly">Monthly</option>
      </select>

      <p style="margin-top:1rem;"><strong>Storage</strong></p>
      <select name="storage">
        <option value="local">Local (this WP)</option>
        <option value="drive" disabled>Google Drive (coming soon)</option>
      </select>

      <p style="margin-top:1.5rem;">
        <button class="button button-primary">Save Settings</button>
      </p>
    </form>
  </div>

  <div class="sg-settings-right">
    <div class="sg-coming-soon">
      <h3>Remote Control (Web App)</h3>
      <p>COMING SOON — you'll be able to see all your WordPress sites linked to this account here.</p>
    </div>
  </div>
</div>
