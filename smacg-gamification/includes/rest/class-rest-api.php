add_action( 'rest_api_init', function () {
    register_rest_route( 'smacg/v1', '/user-level', [
        'methods'             => 'GET',
        'permission_callback' => 'is_user_logged_in',
        'callback'            => fn() => rest_ensure_response(
            smacg_get_user_level( get_current_user_id() )
        ),
    ] );
} );
