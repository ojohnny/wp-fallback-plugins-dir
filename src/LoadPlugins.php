<?php

namespace WpFallbackPluginsDir;

class LoadPlugins
{
    static function from_path($fallback_plugins_path)
    {
        $fallback_plugins_path = rtrim(
            wp_normalize_path($fallback_plugins_path),
            "/"
        );

        if (!function_exists("get_plugin_data")) {
            require_once ABSPATH . "/wp-admin/includes/plugin.php";
        }

        $active_and_valid_plugins = wp_get_active_and_valid_plugins();
        $active_network_plugins = is_multisite()
            ? wp_get_active_and_valid_plugins()
            : [];
        $active_plugins = get_option("active_plugins", []);

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
            $fallback_plugin_id = "fallback-plugin/{$plugin_relpath}";

            if (in_array($fallback_plugin_id, $active_plugins)) {
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

            if (!empty($plugin["Name"])) {
                /* We should load this plugin */
                $fallback_plugin_files[] = $file;
                $fallback_plugin_data[$fallback_plugin_id] = $plugin;
                $fallback_plugins[] = $fallback_plugin_id;
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

                    $url = content_url(
                        substr($fallback_plugins_path, strlen(WP_CONTENT_DIR)) .
                            "/$relpath"
                    );

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

        foreach ($fallback_plugin_files as $fallback_plugin_file) {
            include $fallback_plugin_file;
        }

        do_action("plugins_loaded");
    }
}
