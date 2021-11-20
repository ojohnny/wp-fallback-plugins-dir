<?php

namespace WpFallbackPluginsDir;
use MatthiasMullie\PathConverter\Converter;

class LoadPlugins
{
    static function from_path($fallback_plugins_path)
    {
        $fallback_plugins_path = rtrim(
            wp_normalize_path($fallback_plugins_path),
            "/"
        );

        /* The plugins.php page outputs warnings when viewed on a
         * multisite network unless the path is relative to
         * WP_PLUGIN_DIR. */
        $converter = new Converter($fallback_plugins_path, WP_PLUGIN_DIR);

        if (!function_exists("get_plugin_data")) {
            require_once ABSPATH . "/wp-admin/includes/plugin.php";
        }

        $active_and_valid_plugins = wp_get_active_and_valid_plugins();
        $active_network_plugins = is_multisite()
            ? wp_get_active_network_plugins()
            : [];

        /* Normalize the plugin names to be relative to a plugins directory. */
        $active_plugins = array_map(function ($path) {
            $file = basename($path);
            $dir = basename(dirname($path));
            if ($file === $path || $dir === "plugins") {
                return $path;
            }
            return "{$dir}/{$file}";
        }, get_option("active_plugins", []));

        $fallback_plugins = [];
        $fallback_plugin_data = [];
        $fallback_plugin_files = [];
        foreach (glob($fallback_plugins_path . "/*/*.php") as $file) {
            if (!is_readable($file)) {
                continue;
            }

            $plugin_relpath = substr(
                $file,
                strlen($fallback_plugins_path . "/")
            );

            /* $active_plugins is normalized to 'relpath' */
            if (in_array($plugin_relpath, $active_plugins)) {
                /* Plugin already loaded as a fallback */
                continue;
            }

            if (
                in_array(
                    WP_PLUGIN_DIR . "/{$plugin_relpath}",
                    $active_and_valid_plugins
                )
            ) {
                /* Plugin is loaded by WordPress */
                continue;
            }

            if (
                in_array(
                    WP_PLUGIN_DIR . "/{$plugin_relpath}",
                    $active_network_plugins
                )
            ) {
                /* Plugin is loaded by WordPress */
                continue;
            }

            $plugin = get_plugin_data($file);

            $file_rel_plugin_dir = $converter->convert($plugin_relpath);

            if (!empty($plugin["Name"])) {
                /* We should load this plugin */
                $fallback_plugin_files[] = $file;
                $fallback_plugin_data[$file_rel_plugin_dir] = $plugin;
                $fallback_plugins[] = $file_rel_plugin_dir;
            }
        }

        add_filter(
            "plugins_url",
            function ($url, $path, $plugin) use ($fallback_plugins_path) {
                if (strpos($plugin, $fallback_plugins_path) === 0) {
                    $relpath = ltrim(
                        dirname(
                            substr($plugin, strlen($fallback_plugins_path))
                        ),
                        "/"
                    );

                    /* Handle case when path is inside a symlinked theme */
                    $template_dir = realpath(get_template_directory());
                    if (strpos($fallback_plugins_path, $template_dir) === 0) {
                        $url =
                            get_template_directory_uri() .
                            substr(
                                $fallback_plugins_path,
                                strlen($template_dir)
                            ) .
                            "/$relpath";
                    } else {
                        $url = content_url(
                            substr(
                                $fallback_plugins_path,
                                strlen(WP_CONTENT_DIR)
                            ) . "/$relpath"
                        );
                    }

                    if ($path && is_string($path)) {
                        $url .= "/" . ltrim($path, "/");
                    }
                }

                return $url;
            },
            10,
            3
        );

        add_filter("all_plugins", function ($plugins) use (
            $fallback_plugin_data
        ) {
            /* Hide the fallback plugins from the Network Plugins screen,
             * as they are not necessarily available to all sites. */
            $current_screen = get_current_screen();
            if ($current_screen && $current_screen->in_admin("network")) {
                return $plugins;
            }
            return array_merge($plugins, $fallback_plugin_data);
        });

        add_filter("option_active_plugins", function ($active_plugins) use (
            $fallback_plugins
        ) {
            $funcs = array_column(debug_backtrace(), "function");
            if (in_array("validate_active_plugins", $funcs)) {
                return $active_plugins;
            }
            if (in_array("deactivate_plugins", $funcs)) {
                return $active_plugins;
            }
            if (in_array("activate_plugin", $funcs)) {
                return $active_plugins;
            }
            return array_merge($active_plugins, $fallback_plugins);
        });

        $should_do_plugins_loaded =
            did_action("plugins_loaded") && !doing_action("plugins_loaded");

        /* Don't trigger plugins_loaded actions a second time for normal plugins. */
        if ($should_do_plugins_loaded) {
            remove_all_actions("plugins_loaded");
        }

        foreach ($fallback_plugin_files as $fallback_plugin_file) {
            include $fallback_plugin_file;
        }

        if ($should_do_plugins_loaded) {
            do_action("plugins_loaded");
        }
    }
}
