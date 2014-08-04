<?php
/*
Plugin Name: Submenu Widget
Version: 0.3.5
Description: Show the submenu as a widget in a sidebar or use this shortcode to display one inline: <code>[submenu menu="<strong>MENU-NAME</strong>" id=<strong>INT</strong> depth=<strong>INT</strong>]</code> or <code>[submenu menu="<strong>MENU-NAME</strong>" slug="<strong>page-slug</strong>" depth=<strong>INT</strong>]</code> - slug takes precedence over ID.  If neither are used, the current $post->ID will be used. 
Author: Eric King
Author URI: http://webdeveric.com/
*/

// http://wordpress.stackexchange.com/questions/2802/display-a-portion-branch-of-the-menu-tree-using-wp-nav-menu

class SubmenuWidget extends WP_Widget
{
    protected static $in_widget = false;

    function __construct( $name = 'Submenu Widget' )
    {
        parent::__construct( false, $name );
    }

    public static function in_widget()
    {
        return self::$in_widget;
    }

    function widget( $args, $instance )
    {
        if ( ! isset( $instance['menu'] ) || $instance['menu'] == '' )
            return;

        self::$in_widget = true;

        extract( $args );

        $menu_items = (array)wp_get_nav_menu_items( wp_get_nav_menu_object( $instance['menu'] )->term_id );

        _wp_menu_item_classes_by_context( $menu_items );

        $parent_ids           = array();
        $active_items         = array();
        $items_with_children  = array();

        $first_ancestor_title = null;
        $first_ancestor_url   = './';

        $menu_classes_to_check = array(
            'current-menu-item'
        );

        if ( $instance['show_parents'] )
            $menu_classes_to_check[] = 'current-menu-parent';

        if ( $instance['show_ancestors'] )
            $menu_classes_to_check[] = 'current-menu-ancestor';

        foreach ( $menu_items as &$item ) {

            $save_id = false;

            if ( $instance['expand_all_submenus'] ) {

                $save_id = in_array( $item->menu_item_parent, $parent_ids ) || (bool)array_intersect( $menu_classes_to_check, $item->classes );

            } else {

                $save_id = (bool)array_intersect( $menu_classes_to_check, $item->classes );

            }

            if ( $save_id ) {

                $parent_ids[] = $item->ID;

                if ( ! isset( $first_ancestor_title ) ) {

                    $first_ancestor_title = $item->title;

                    if ( isset( $item->url ) && $item->url != '' )
                        $first_ancestor_url = $item->url;

                }

            }

            if ( $item->menu_item_parent )
                $items_with_children[ $item->menu_item_parent ] = true;

        }

        foreach ( $menu_items as &$item ) {

            $intersection = array_intersect( $parent_ids, array( $item->menu_item_parent, $item->ID ) );

            if ( ! empty( $intersection ) && $parent_ids[0] != $item->ID )
                $active_items[] = $item;

            if ( isset( $items_with_children[ $item->ID ] ) )
                $item->classes[] = 'menu-item-has-children';

        }

        unset($items_with_children);

        $walk_nav_menu_tree_args = apply_filters('submenu_widget_walk_nav_menu_tree_args', new stdClass);

        $output = walk_nav_menu_tree( $active_items, $instance['depth'], $walk_nav_menu_tree_args );

        if ( $output != '' ) {

            $widget_frame_classes = apply_filters('submenu_widget_frame_classes', array('submenu-widget-frame'), $args, $instance, $active_items );

            echo $before_widget, '<div class="', implode(' ', $widget_frame_classes), '">';
            printf('%s<a href="%s">%s</a>%s', $before_title, $first_ancestor_url, $first_ancestor_title, $after_title );
            echo '<div class="submenu"><ul>',  $output, '</ul></div></div>', $after_widget;
        }

        self::$in_widget = false;
    }

    /*
        Added shortcode - Eric King - 2011-10-07
    */
    public static function getSubMenu( $atts, $content = null, $code = '' )
    {
        global $post;
        $atts = shortcode_atts( array(
            'menu'        => '',
            'id'        => $post->ID,
            'depth'        => 0,
            'classname'    => 'in-content-submenu',
            // @added 2013-10-30
            'slug'        => null
        ), $atts );

        extract( $atts );

        // @added 2013-10-30
        if ( isset( $slug ) ) {

            global $wpdb;
            $post_id = $wpdb->get_var( $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_name = %s and post_status = 'publish' LIMIT 1", $slug ) );
            if ( isset( $post_id ) ) {
                $id = intval( $post_id );
                unset( $post_id );
            }
            unset( $slug );

        }

        $menu_items = wp_get_nav_menu_items( wp_get_nav_menu_object( $menu )->term_id );

        $parent_ids   = array();
        $active_items = array();

        $id = isset( $id ) ? intval( $id ) : 0;

        // Find the starting menu item.
        if ( $id > 0 ) {

            foreach ( $menu_items as &$item ) {
                if ( $item->object_id == $id ) {
                    $parent_ids[] = $item->ID;
                    break;
                }
            }

        } else {

            foreach ( $menu_items as &$item ) {
                if ( $item->post_parent == $id )
                    $parent_ids[] = $item->ID;
            }

        }

        // Find child menu items.
        foreach ( $menu_items as &$item ) {
            if ( in_array( $item->menu_item_parent, $parent_ids ) )
                $parent_ids[] = $item->ID;
        }

        // Save menu items if they have the correct parent.
        foreach( $menu_items as &$item ) {
            $intersection = array_intersect( $parent_ids, array( $item->menu_item_parent, $item->ID ) );
            if ( ! empty( $intersection ) && $parent_ids[0] != $item->ID )
                $active_items[] = $item;
        }

        $walk_nav_menu_tree_args = apply_filters('submenu_widget_walk_nav_menu_tree_args', new stdClass);

        $output = walk_nav_menu_tree( $active_items, $depth, $walk_nav_menu_tree_args );

        return $output != '' ? sprintf('<div class="%s"><ul>%s</ul></div>', $classname, $output ) : '';
    }

    function update( $new_instance, $old_instance )
    {
        $instance = $old_instance;
        $instance['menu'] = $new_instance['menu'];
        $instance['depth'] = intval( $new_instance['depth'] );
        $instance['expand_all_submenus'] = isset( $new_instance['expand_all_submenus'] );
        $instance['show_parents'] = isset( $new_instance['show_parents'] );
        $instance['show_ancestors'] = isset( $new_instance['show_ancestors'] );
        return $instance;
    }

    function form( $instance )
    {
        $menu = $instance['menu'];
        $depth = isset( $instance['depth'] ) ? intval( $instance['depth'] ) : 0;
        $expand_all_submenus = isset( $instance['expand_all_submenus'] ) ? $instance['expand_all_submenus'] : false;
        $show_parents = isset( $instance['show_parents'] ) ? $instance['show_parents'] : false;
        $show_ancestors = isset( $instance['show_ancestors'] ) ? $instance['show_ancestors'] : false;
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('menu'); ?>"><?php _e('Menu:'); ?></label>
            <?php
                $menus = get_terms('nav_menu');
                if ( count( $menus ) > 0 ):?>
                    <select id="<?php echo $this->get_field_id('menu'); ?>" name="<?php echo $this->get_field_name('menu'); ?>">
                    <option value=""></option>
                    <?php
                    foreach( $menus as $m ) {
                        printf('<option value="%s" %s>%s</option>', $m->slug, selected( $menu, $m->slug, false ), $m->name );
                    }
                    ?>
                    </select>
                <?php else:
                    echo '<p>No menus defined</p>';
                endif;
            ?>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('depth'); ?>"><?php _e('Depth:'); ?></label>
            <input type="number" min="-1" max="99" required id="<?php echo $this->get_field_id('depth'); ?>" name="<?php echo $this->get_field_name('depth'); ?>" maxlength="2" size="2" value="<?php echo $depth; ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('expand_all_submenus'); ?>"><?php _e('Expand All Submenus:'); ?></label>
            <input type="checkbox" id="<?php echo $this->get_field_id('expand_all_submenus'); ?>" name="<?php echo $this->get_field_name('expand_all_submenus'); ?>" <?php checked( $expand_all_submenus, true ); ?> value="true" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('show_parents'); ?>"><?php _e('Show Parent Items:'); ?></label>
            <input type="checkbox" id="<?php echo $this->get_field_id('show_parents'); ?>" name="<?php echo $this->get_field_name('show_parents'); ?>" <?php checked( $show_parents, true ); ?> value="true" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('show_ancestors'); ?>"><?php _e('Show Ancestor Items:'); ?></label>
            <input type="checkbox" id="<?php echo $this->get_field_id('show_ancestors'); ?>" name="<?php echo $this->get_field_name('show_ancestors'); ?>" <?php checked( $show_ancestors, true ); ?> value="true" />
        </p>
    <?php
    }
}

add_shortcode('submenu', array( 'SubmenuWidget', 'getSubMenu' ) );

function SubmenuWidget_widgets_init()
{
    register_widget('SubmenuWidget');
}
add_action('widgets_init', 'SubmenuWidget_widgets_init');
