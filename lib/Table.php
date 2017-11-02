<?php

namespace WPCustomTables;

/**
 * A Base WordPress Database Table class
 *
 * @author  JJJ
 * @link    https://jjj.blog
 * @version 1.1.0
 * @license https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

if (! class_exists('Table')) :
/**
 * The base WordPress database table class, which facilitates the creation of
 * and schema changes to individual database tables.
 *
 * This class is intended to be extended for each unique database table,
 * including global multisite tables and users tables.
 *
 * It exists to make managing database tables in WordPress as easy as possible.
 *
 * Extending this class comes with several automatic benefits:
 * - Activation hook makes it great for plugins
 * - Tables store their versions in the database independently
 * - Tables upgrade via independent upgrade abstract methods
 * - Multisite friendly - site tables switch on "switch_blog" action
 *
 * @since 1.0.0
 */
    abstract class Table
    {

        /**
     * @var string Table name, without the global table prefix
     */
        protected $name = '';

        /**
     * @var int Database version
     */
        protected $version = 0;

        /**
     * @var boolean Is this table for a site, or global
     */
        protected $global = false;

        /**
     * @var string Database version key (saved in _options or _sitemeta)
     */
        protected $db_version_key = '';

        /**
     * @var string Current database version
     */
        protected $db_version = 0;

        /**
     * @var string Table name
     */
        protected $table_name = '';

        /**
     * @var string Table schema
     */
        protected $schema = '';

        /**
     * @var string Database character-set & collation for table
     */
        protected $charset_collation = '';

        /**
     * @var WPDB Database object (usually $GLOBALS['wpdb'])
     */
        protected $db = false;

        /** Methods ***************************************************************/

        /**
     * Hook into queries, admin screens, and more!
     *
     * @since 1.0.0
     */
        public function __construct()
        {

            // Bail if no database object or table name
            if (empty($GLOBALS['wpdb']) || empty($this->name)) {
                return;
            }

            // Setup the database
            $this->setDb();

            // Get the version of he table currently in the database
            $this->getDbVersion();

            // Add the table to the object
            $this->setWpdbTables();

            // Setup the database schema
            $this->setSchema();

            // Add hooks to WordPress actions
            $this->addHooks();
        }

        /** Abstract **************************************************************/

        /**
     * Setup this database table
     *
     * @since 1.0.0
     */
        abstract protected function setSchema();

        /**
     * Upgrade this database table
     *
     * @since 1.0.0
     */
        abstract protected function upgrade();

        /** Public ****************************************************************/

        /**
     * Update table version & references.
     *
     * Hooked to the "switch_blog" action.
     *
     * @since 1.0.0
     *
     * @param int $site_id
     */
        public function switchBlog($site_id = 0)
        {

            // Update DB version based on the current site
            if (false === $this->global) {
                $this->db_version = get_blog_option($site_id, $this->db_version_key, false);
            }

            // Update table references based on th current site
            $this->setWpdbTables();
        }

        /**
     * Maybe upgrade the database table. Handles creation & schema changes.
     *
     * Hooked to the "admin_init" action.
     *
     * @since 1.0.0
     */
        public function maybeUpgrade()
        {

            // Bail if no upgrade needed
            if (version_compare((int) $this->db_version, (int) $this->version, '>=')) {
                return;
            }

            // Include file with dbDelta() for create/upgrade usages
            if (! function_exists('dbDelta')) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            }

            // Bail if global and upgrading global tables is not allowed
            if (( true === $this->global ) && ! wp_should_upgrade_global_tables()) {
                return;
            }

            // Create or upgrade?
            $this->exists()
            ? $this->upgrade()
            : $this->create();

            // Set the database version
            if ($this->exists()) {
                $this->setDbVersion();
            }
        }

        /** Private ***************************************************************/

        /**
     * Setup the necessary WPDB variables
     *
     * @since 1.0.0
     */
        private function setDb()
        {

            // Setup database
            $this->db   = $GLOBALS['wpdb'];
            $this->name = sanitize_key($this->name);

            // Maybe create database key
            if (empty($this->db_version_key)) {
                $this->db_version_key = "wpdb_{$this->name}_version";
            }
        }

        /**
     * Modify the database object and add the table to it
     *
     * This is necessary to do directly because WordPress does have a mechanism
     * for manipulating them safely. It's pretty fragile, but oh well.
     *
     * @since 1.0.0
     */
        private function setWpdbTables()
        {

            // Global
            if (true === $this->global) {
                $prefix                       = $this->db->get_blog_prefix(0);
                $this->db->{$this->name}      = "{$prefix}{$this->name}";
                $this->db->ms_global_tables[] = $this->name;

            // Site
            } else {
                $prefix                  = $this->db->get_blog_prefix(null);
                $this->db->{$this->name} = "{$prefix}{$this->name}";
                $this->db->tables[]      = $this->name;
            }

            // Set the table name locally
            $this->table_name = $this->db->{$this->name};

            // Charset
            if (! empty($this->db->charset)) {
                $this->charset_collation = "DEFAULT CHARACTER SET {$this->db->charset}";
            }

            // Collation
            if (! empty($this->db->collate)) {
                $this->charset_collation .= " COLLATE {$this->db->collate}";
            }
        }

        /**
     * Set the database version to the table version.
     *
     * Saves global table version to "wp_sitemeta" to the main network
     *
     * @since 1.0.0
     */
        private function setDbVersion()
        {

            // Set the class version
            $this->db_version = $this->version;

            // Update the DB version
            ( true === $this->global )
            ? update_network_option(null, $this->db_version_key, $this->version)
            :         update_option($this->db_version_key, $this->version);
        }

        /**
     * Get the table version from the database.
     *
     * Gets global table version from "wp_sitemeta" to the main network
     *
     * @since 1.0.0
     */
        private function getDbVersion()
        {
            $this->db_version = ( true === $this->global )
            ? get_network_option(null, $this->db_version_key, false)
            :         get_option($this->db_version_key, false);
        }

        /**
     * Add class hooks to WordPress actions
     *
     * @since 1.0.0
     */
        private function addHooks()
        {

            // Activation hook
            register_activation_hook(__FILE__, array( $this, 'maybeUpgrade' ));

            // Add table to the global database object
            add_action('switch_blog', array( $this, 'switchBlog'   ));
            add_action('admin_init', array( $this, 'maybeUpgrade' ));
        }

        /**
     * Create the table
     *
     * @since 1.0.0
     */
        protected function create()
        {

            // Run CREATE TABLE query
            $created = dbDelta(array( "CREATE TABLE {$this->table_name} ( {$this->schema} ) {$this->charset_collation};" ));

            // Was anything created?
            return ! empty($created);
        }

        /**
     * Check if table already exists
     *
     * @since 1.0.0
     *
     * @return bool
     */
        private function exists()
        {
            $query       = "SHOW TABLES LIKE %s";
            $like        = $this->db->esc_like($this->table_name);
            $prepared    = $this->db->prepare($query, $like);
            $table_exist = $this->db->get_var($prepared);

            return ! empty($table_exist);
        }

        public function getTableName()
        {
            return $this->table_name;
        }
    }
endif;
