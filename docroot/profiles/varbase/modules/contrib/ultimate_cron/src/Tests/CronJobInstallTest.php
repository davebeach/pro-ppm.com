<?php

namespace Drupal\ultimate_cron\Tests;

use Drupal\Core\Url;
use Drupal\simpletest\WebTestBase;
use Drupal\ultimate_cron\CronRule;
use Drupal\ultimate_cron\Entity\CronJob;

/**
 * Cron Job Form Testing
 *
 * @group ultimate_cron
 */
class CronJobInstallTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('ultimate_cron');

  /**
   * A user with permission to create and edit books and to administer blocks.
   *
   * @var object
   */
  protected $admin_user;

  /**
   * Tests adding and editing a cron job.
   */
  function testManageJob() {
    // Create user with correct permission.
    $this->admin_user = $this->drupalCreateUser(array('administer ultimate cron'));
    $this->drupalLogin($this->admin_user);

    // Check default modules
    \Drupal::service('module_installer')->install(array('field'));
    $this->drupalGet('admin/config/system/cron/jobs');
    $this->assertText('Purges deleted Field API data');
    $this->assertText('Cleanup (caches, batch, flood, temp-files, etc.)');
    $this->assertNoText('Deletes temporary files');

    // Install new module.
    \Drupal::service('module_installer')->install(array('file'));
    $this->drupalGet('admin/config/system/cron/jobs');
    $this->assertText('Deletes temporary files');

    // Uninstall new module.
    \Drupal::service('module_installer')->uninstall(array('file'));
    $this->drupalGet('admin/config/system/cron/jobs');
    $this->assertNoText('Deletes temporary files');
  }

  /**
   * Tests the requirements checking of ultimate_cron.
   */
  public function testRequirements() {
    $element = ultimate_cron_requirements('runtime')['cron_jobs'];
    $this->assertEqual($element['value'], t("Cron is running properly."));
    $this->assertEqual($element['severity'], REQUIREMENT_OK);


    $values = array(
      'title' => 'ultimate cron fake cronjob title',
      'id' => 'ultimate_cron_fake_job',
      'module' => 'ultimate_cron_fake',
      'callback' => 'ultimate_cron_fake_cron',
    );

    $job = new CronJob($values, 'ultimate_cron_job');
    $job->save();

    \Drupal::service('cron')->run();

    // Generate an initial scheduled cron time.
    $cron = CronRule::factory('*/15+@ * * * *', time(), $job->getUniqueID() & 0xff);
    $scheduled_cron_time = $cron->getLastSchedule();
    // Generate a new start time by adding two seconds to the initial scheduled cron time.
    $log_entry_past = $scheduled_cron_time - 10000;
    db_update('ultimate_cron_log')
      ->fields([
        'start_time' => $log_entry_past,
      ])
      ->condition('name', $values['id'])
      ->execute();

    // Check run counter, at this point there should be 0 run.
    $this->assertEqual(1, \Drupal::state()->get('ultimate_cron.cron_run_counter'), 'Job has run once.');
    $this->assertTrue($job->isBehindSchedule(), 'Job is behind schedule.');

    $element = ultimate_cron_requirements('runtime')['cron_jobs'];
    $this->assertEqual($element['value'], '1 job is behind schedule', '"1 job is behind schedule." is displayed');
    $this->assertEqual($element['description'], 'Some jobs are behind their schedule. Please check if <a href="' .
      Url::fromRoute('system.cron', ['key' => \Drupal::state()->get('system.cron_key')])->toString() .
      '">Cron</a> is running properly.', 'Description is correct.');
    $this->assertEqual($element['severity'], REQUIREMENT_WARNING, 'Severity is of level "Error"');
  }

}
