<?php
/**
 * Plugin Name: 随机头像-6KE论坛出品
 * Plugin URI: https://6.ke/
 * Description: 为新用户分配一个随机头像。
 * Version: 1.0.0
 * Author: 6KE论坛
 * Author URI: https://6.ke/
 */
// 定义头像目录路径
$avatars_dir = get_stylesheet_directory() . '/avatars/';

// 如果目录不存在，则创建头像目录
if ( ! file_exists( $avatars_dir ) ) {
    wp_mkdir_p( $avatars_dir );
}

// 获取数据库表名
function random_avatar_get_table_name() {
    $table_name = get_option( 'random_avatar_table_name' );
    if ( empty( $table_name ) ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'usermeta';
    }
    return $table_name;
}

// 定义头像列表
function random_avatar_get_avatar_files() {
    $avatars_dir = get_stylesheet_directory() . '/avatars/';
    $avatar_files = array_diff( scandir( $avatars_dir ), array( '.', '..' ) );

    // 排除已经分配过的头像文件
    global $wpdb;
    $table_name = random_avatar_get_table_name();
    $used_avatar_files = $wpdb->get_col( "SELECT meta_value FROM {$table_name} WHERE meta_key = 'custom_avatar'" );
    if ( ! empty( $used_avatar_files ) ) {
        $avatar_files = array_diff( $avatar_files, $used_avatar_files );
    }

    return $avatar_files;
}

// 为新用户分配一个随机头像，并将头像 URL 写入到 wp_usermeta 数据库表的 custom_avatar 字段中
function random_avatar_assign_avatar( $user_id ) {
    $avatar_files = random_avatar_get_avatar_files();
    if ( ! empty( $avatar_files ) ) {
        $random_avatar = $avatar_files[ array_rand( $avatar_files ) ];
        update_user_meta( $user_id, 'avatar', $random_avatar );
        update_user_meta( $user_id, 'custom_avatar', get_stylesheet_directory_uri() . '/avatars/' . $random_avatar );

        $user_url = get_stylesheet_directory_uri() . '/avatars/' . $random_avatar;
        $user_url = esc_url_raw( $user_url );

        global $wpdb;
        $table_name = random_avatar_get_table_name();
        $wpdb->update(
            $table_name,
            array( 'meta_value' => $user_url ),
            array( 'user_id' => $user_id, 'meta_key' => 'custom_avatar' ),
            array( '%s' ),
            array( '%d', '%s' )
        );
    }
}

// 显示用户头像
function random_avatar_display_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
    if ( is_numeric( $id_or_email ) ) {
        $user_id = (int) $id_or_email;
    } elseif ( is_string( $id_or_email ) && ( $user = get_user_by( 'email', $id_or_email ) ) ) {
        $user_id = $user->ID;
    } elseif ( is_object( $id_or_email ) && ! empty( $id_or_email->user_id ) ) {
        $user_id = (int) $id_or_email->user_id;
    }

    if ( isset( $user_id ) ) {
        $avatar_file = get_user_meta( $user_id, 'avatar', true );
        if ( ! empty( $avatar_file ) ) {
            $avatar_url = get_stylesheet_directory_uri() . '/avatars/' . $avatar_file;
            $avatar = "<img alt='{$alt}' src='{$avatar_url}' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' />";
        }
    }

    return $avatar;
}
add_filter( 'get_avatar', 'random_avatar_display_avatar', 10, 5 );

// 为新用户分配一个随机头像
add_action( 'user_register', 'random_avatar_assign_avatar' );

// 添加设置页面
function random_avatar_settings_page() {
    if ( get_option( 'random_avatar_authorized' ) ) {
        // 显示设置表单
        $avatars_dir = get_stylesheet_directory() . '/avatars/';
        $avatar_files = random_avatar_get_avatar_files();

        // 保存设置
        if ( isset( $_POST['random_avatar_submit'] ) ) {
            // 保存头像文件
            if ( isset( $_FILES['random_avatar_files'] ) && ! empty( $_FILES['random_avatar_files']['name'][0] ) ) {
                $allowed_exts = array( 'jpg', 'jpeg', 'png', 'gif' );
                foreach ( $_FILES['random_avatar_files']['name'] as $index => $file_name ) {
                    $file_tmp = $_FILES['random_avatar_files']['tmp_name'][ $index ];
                    $file_type = $_FILES['random_avatar_files']['type'][ $index ];
                    $file_size = $_FILES['random_avatar_files']['size'][ $index ];
                    $file_ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

                    if ( in_array( $file_ext, $allowed_exts ) && $file_size <= 1024 * 1024 ) {
                        $new_file_name = uniqid() . '.' . $file_ext;
                        $new_file_path = $avatars_dir . $new_file_name;
                        move_uploaded_file( $file_tmp, $new_file_path );
                        $avatar_files[] = $new_file_name;
                        update_option( 'random_avatar_files', $avatar_files );
                    }
                }
            }

            // 保存数据表前缀
            if ( isset( $_POST['random_avatar_table_name'] ) && ! empty( $_POST['random_avatar_table_name'] ) ) {
                $table_name = sanitize_text_field( $_POST['random_avatar_table_name'] );
                update_option( 'random_avatar_table_name', $table_name );
            }
        }

        // 删除头像文件
        if ( isset( $_POST['random_avatar_delete'] ) ) {
            $delete_file = $_POST['random_avatar_delete'];
            $delete_path = $avatars_dir . $delete_file;
            if ( file_exists( $delete_path ) ) {
                unlink( $delete_path );
                $avatar_files = array_diff( $avatar_files, array( $delete_file ) );
                update_option( 'random_avatar_files', $avatar_files );
            }
        }

        // 显示设置页面
        $avatar_files = random_avatar_get_avatar_files();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( '随机头像设置', 'random-avatar' ); ?></h1>

            <form method="post" enctype="multipart/form-data">
                <h2><?php esc_html_e( '添加新头像（https://6.ke）', 'random-avatar' ); ?></h2>
                <p>
                    <input type="file" name="random_avatar_files[]" multiple />
                </p>
                <p>
                    <input type="submit" name="random_avatar_submit" value="<?php esc_attr_e( '上传', 'random-avatar' ); ?>" class="button button-primary" />
                </p>
            </form>

            <h2><?php esc_html_e( '数据表设置（https://6.ke）', 'random-avatar' ); ?></h2>
            <form method="post">
                <p>
                    <label for="random_avatar_table_name"><?php esc_html_e( '数据表前缀（https://6.ke）', 'random-avatar' ); ?></label>
                    <input type="text" name="random_avatar_table_name" id="random_avatar_table_name" value="<?php echo esc_attr( random_avatar_get_table_name() ); ?>" />
                    <a>这一项不懂别瞎改，不懂群里问去论坛问：https://6.ke</a>
                </p>
                <p>
                    <?php wp_nonce_field( 'random_avatar_update_table_name', 'random_avatar_table_name_nonce' ); ?>
                    <input type="submit" name="random_avatar_table_name_submit" value="<?php esc_attr_e( '保存', 'random-avatar' ); ?>" class="button button-primary" />
                </p>
            </form>

            <h2 class="nav-tab-wrapper">
                <a href="#unused" class="nav-tab nav-tab-active"><?php esc_html_e( '头像列表（https://6.ke）', 'random-avatar' ); ?></a>
            </h2>

            <div id="used" class="tab-content">
                <?php
                global $wpdb;
                $table_name = random_avatar_get_table_name();
                $used_avatar_files = $wpdb->get_col( "SELECT meta_value FROM {$table_name} WHERE meta_key = 'custom_avatar'" );
                if ( ! empty( $used_avatar_files ) ) :
                    $used_avatar_files = array_unique( $used_avatar_files );
                    ?>
                    <ul>
                        <?php foreach ( $used_avatar_files as $avatar_file ) : ?>
                            <li>
                                <img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/avatars/' . $avatar_file ); ?>" alt="<?php echo esc_attr( $avatar_file ); ?>" width="50" height="50" />
                                <?php echo esc_html( $avatar_file ); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p><?php esc_html_e( '还没有用户使用过头像。', 'random-avatar' ); ?></p>
                <?php endif; ?>
            </div>

            <div id="unused" class="tab-content active">
                <?php if ( ! empty( $avatar_files ) ) : ?>
                    <ul>
                        <?php foreach ( $avatar_files as $avatar_file ) : ?>
                            <li>
                                <img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/avatars/' . $avatar_file ); ?>" alt="<?php echo esc_attr( $avatar_file ); ?>" width="50" height="50" />
                                <?php echo esc_html( $avatar_file ); ?>
                                <form method="post" style="display: inline-block;">
                                    <input type="hidden" name="random_avatar_delete" value="<?php echo esc_attr( $avatar_file ); ?>" />
                                    <button type="submit" class="button"><?php esc_html_e( '删除', 'random-avatar' ); ?></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p><?php esc_html_e( '还没有可用的头像。', 'random-avatar' ); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .nav-tab-wrapper {
                border-bottom: 1px solid #ccc;
                margin-bottom: 20px;
                padding-left: 0;
            }

            .nav-tab {
                background-color: #f1f1f1;
                border: 1px solid #ccc;
                border-bottom: none;
                color: #444;
                display: inline-block;
                line-height: 1.5;
                margin-right: 5px;
                padding: 6px 12px;
                text-decoration: none;
            }

            .nav-tab-active {
                background-color: #fff;
                border-color: #ccc;
                border-bottom: none;
            }

            .tab-content {
                display: none;
            }

            .tab-content.active {
                display: block;
            }
        </style>

        <script>
            jQuery(document).ready(function($) {
                $('.nav-tab').click(function(event) {
                    event.preventDefault();
                    $('.nav-tab').removeClass('nav-tab-active');
                    $(this).addClass('nav-tab-active');
                    $('.tab-content').removeClass('active');
                    $($(this).attr('href')).addClass('active');
                });
            });
        </script>
        <?php
    } else {
        // 显示卡密验证表单
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( '随机头像授权', 'random-avatar' ); ?></h1>

            <form method="post">
                <p><?php esc_html_e( '请输入卡密以授权插件。', 'random-avatar' ); ?></p>
                <p>
                    <input type="text" name="random_avatar_card_key" id="random_avatar_card_key" />
                    <input type="submit" name="random_avatar_card_key_submit" value="<?php esc_attr_e( '验证', 'random-avatar' ); ?>" class="button button-primary" />
                </p>
            </form>
        </div>
        <?php
    }
}

function random_avatar_add_menu() {
    add_options_page( __( '随机头像设置', 'random-avatar' ), __( '随机头像', 'random-avatar' ), 'manage_options', 'random-avatar', 'random_avatar_settings_page' );
}
add_action( 'admin_menu', 'random_avatar_add_menu' );

// 验证授权状态
function random_avatar_check_authorization() {
    if ( ! get_option( 'random_avatar_authorized' ) ) {
        if ( isset( $_POST['random_avatar_card_key_submit'] ) ) {
            $card_key = sanitize_text_field( $_POST['random_avatar_card_key'] );
            $verify_url = 'https://6.ke/wp-json/card_key_generator/v1/verify_card_key?card_key_code=' . $card_key;
            $response = wp_remote_get( $verify_url );
            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) == 200 ) {
                $verify_result = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( $verify_result['success'] && $verify_result['data']['status'] ) {
                    update_option( 'random_avatar_authorized', true ); //更新选项
                    echo '<div class="updated"><p>' . esc_html__( '卡密验证成功，插件已被授权。', 'random-avatar' ) . '</p></div>';
                } else {
                    echo '<div class="error"><p>' . esc_html__( '无效的卡密。', 'random-avatar' ) . '</p></div>';
                }
            } else {
                echo '<div class="error"><p>' . esc_html__( '无法验证卡密。', 'random-avatar' ) . '</p></div>';
            }
        }

        // wp_die( __( '随机头像插件未被授权。', 'random-avatar' ) );
    }
}
add_action( 'admin_init', 'random_avatar_check_authorization' );
