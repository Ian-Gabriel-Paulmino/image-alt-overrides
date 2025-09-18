<?php

class IAO_Loader {
    protected $db;
    protected $admin;
    protected $frontend;

    public function __construct() {
        require_once plugin_dir_path( __FILE__ ) . 'class-iao-db.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-iao-admin.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-iao-frontend.php';
        
        $this->db = new IAO_DB();
        $this->admin = new IAO_Admin( $this->db );
        $this->frontend = new IAO_Fronted( $this->db );

    }

    public function run(): void {
        // Init admin and frontend services
        $this->admin->init();
        $this->frontend->init();
    }


}