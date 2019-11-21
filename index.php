<?php

namespace Feedfm;

use Exception;
use mysqli;

/**
 * Notes:
 *
 * 1. I'm presenting the simplest, most basic PHP solution. Not using libraries,
 * abstractions, object models, etc., that would all be present in a real
 * application.
 *
 * 2. There is rudimentary error handling. Ideally, errors should be logged,
 * (and if critical enough, someone alerted) but not shown to the user as that
 * could expose sensitive data.
 *
 * 3. It is bad practice to store db connection details in code. These would
 * normally be in an environment-specific configuration, such as a .env file.
 *
 * 4. In most modern applications, the raw SQL used here would not be necessary
 * at all, as the framework used would have some sort of ORM (ex. Eloquent in
 * Laravel) or query builder.
 *
 * 5. Creating, using & closing a db connection would not normally be in the
 * function, but rather abstracted out and used selectively, as needed.
 */

/**
 * Database connection class. In most cases only a single connection is ever
 * required, so this class could be turned into a singleton.
 *
 * @package Feedfm
 */
class FeedFm {
  // Database connection info.
  protected $host = '';
  protected $user = '';
  protected $pass = '';
  protected $database = '';

  // Database connection itself.
  protected $mysqli = null;

  /**
   * FeedFm constructor.
   *
   * @param $host
   * @param $user
   * @param $pass
   * @param $database
   */
  public function __construct($host, $user, $pass, $database) {
    $this->host = $host;
    $this->user = $user;
    $this->pass = $pass;
    $this->database = $database;
  }

  /**
   * Opens database connection.
   *
   * @throws Exception
   */
  public function open() {
    // Temporarily hide all errors & warnings. Rely on exception for cleaner
    // error handling.
    $errorLevel = error_reporting();
    error_reporting(0);

    $this->mysqli = new mysqli($this->host, $this->user, $this->pass, $this->database);
    if ($this->mysqli->connect_errno) {
      throw new Exception('Error connecting to MySQL: ' . $this->mysqli->connect_error);
    }

    error_reporting($errorLevel);
  }

  /**
   * Closes MySQL connection.
   */
  public function close() {
    if ($this->mysqli === null) {
      return;
    }

    $this->mysqli->close();
  }

  /**
   * Executes query.
   *
   * @param $query
   * @return mixed Query response.
   * @throws Exception
   */
  public function query($query) {
    if ($this->mysqli === null) {
      throw new Exception('No open MySQL connection.');
    }

    // sprintf the query arguments to minimize risk of sql injection attacks
    $args = func_get_args();
    $finalQuery = call_user_func_array('sprintf', $args);

    $response = $this->mysqli->query($finalQuery);
    if (!$response) {
      throw new Exception('Error executing query.');
    }

    return $response;
  }

  /**
   * Magic method. Performs cleanup operations.
   */
  public function __destruct() {
    $this->close();
  }
}

/**
 * Find widgets that contain a tag.
 *
 * @param $tag String
 * @param $offset int
 * @param $max int
 */
function findWidgetsWithTag($tag, $offset, $max) {
  try {
    $db = new FeedFm('localhost', 'root', '', 'feed_fm');
    $db->open();

    $query = '
      SELECT    w.id,
                w.name,
                GROUP_CONCAT(DISTINCT t2.id ORDER BY t2.id ASC) AS tag_ids,
                GROUP_CONCAT(DISTINCT d.id ORDER BY d.id ASC) AS dongle_ids
      FROM      widget w
      -- Inner join tag map and tag tables to filter by given $tag value. The
      -- inner join will exclude widgets that do not have any tags.
      JOIN      widget_tag_map wtm
        ON      wtm.widget_id = w.id
      JOIN      tag t
        ON      t.id = wtm.tag_id
      -- Join tag map and tag tables to get all associated tags. Since we
      -- already know these widgets have at least one tag, we can use
      -- inner/left/right join.
      JOIN      widget_tag_map wtm2
        ON      wtm2.widget_id = w.id
      JOIN      tag t2
        ON      t2.id = wtm2.tag_id
      -- Left join dongle map and dongle tables. We do not want to exclude
      -- widgets that do not have a dongle, so a left join is necessary here.
      LEFT JOIN widget_dongle_map wdm
        ON      wdm.widget_id = w.id
      LEFT JOIN dongle d 
        ON      d.id = wdm.dongle_id
      -- Filter out widgets not associated with given $tag.
      WHERE     t.tag = "%s"
      -- Filter out deleted widgets.
      AND       w.deleted = 0
      GROUP BY  w.id
      LIMIT     %d, %d
    ';

    $response = $db->query($query, $tag, (int) $offset, (int) $max);
    $objects = [];
    while ($row = $response->fetch_object()) {
      /*

        !! Unsure of what 'collection' means in the context of this assignment.
           Uncomment this block if you'd like to get full tag & dongle
           objects instead of just their IDs. !!

        // Pull in the full tag objects.
        $row->tags = [];
        if ($row->tag_ids) {
          $tagQuery = 'SELECT * FROM tag WHERE id IN (%s)';
          $tagsResponse = $db->query($tagQuery, $row->tag_ids);

          while ($tag = $tagsResponse->fetch_object()) {
            $row->tags[] = $tag;
          }
        }
        unset($row->tag_ids);

        // Pull in the full dongle objects.
        $row->dongles = [];
        if ($row->dongle_ids) {
          $dongleQuery = 'SELECT * FROM dongle WHERE id IN (%s)';
          $dongleResponse = $db->query($dongleQuery, $row->dongle_ids);

          while ($dongle = $dongleResponse->fetch_object()) {
            $row->dongles[] = $dongle;
          }
        }
        unset($row->dongle_ids);
      */

      $objects[] = $row;
    }

    return $objects;
  }
  catch (Exception $e) {
    echo $e->getMessage();
  }
}

$widgets = findWidgetsWithTag('tag5', 0, 20);

echo '<pre>';
print_r($widgets);
echo '</pre>';


