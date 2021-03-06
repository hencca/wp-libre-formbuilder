<?php

class WP_Libre_Formbuilder {
  const ERR_FORM_ID_EMPTY = 'You must supply a form id.';
  const FORM_SAVED = 'Form saved succesfully.';

  public static $instance;

  public static function instance() {
    if (is_null(self::$instance)) {
      self::$instance = new WP_Libre_Formbuilder();
    }

    return self::$instance;
  }

  public function __construct() {
    add_action("init", [$this, "registerCPT"]);

    add_filter("user_can_richedit", function($x) {
      if ($GLOBALS["post"]->post_type === "wplfb-field") {
        return false;
      }

      return $x;
    });

    add_action("add_meta_boxes", [$this, "tamperMetaBoxes"]);
    add_action("rest_api_init", [$this, "registerRESTRoutes"]);
  }

  public function registerCPT() {
    register_post_type("wplfb-field", apply_filters("wplfb_cpt_args", [
      "labels" => [
        "name" => _x("Form fields", "post type general name", "wp-libre-formbuilder"),
        "singular_name" => _x("Form field", "post type singular name", "wp-libre-formbuilder")
      ],
      "public" => false,
      "show_ui" => true,
      "show_in_menu" => "edit.php?post_type=wplf-form",
      "capability_type" => apply_filters("wplfb_cpt_capability_type", "post"),
      "capabilities" => apply_filters("wplfb_cpt_capabilities", []),
      "supports" => apply_filters("wplfb_cpt_supports", [
        "title",
        "editor",
        "custom-fields",
        "revisions"
      ]),
      "taxonomies" => apply_filters("wplfb_cpt_taxonomies", []),
      "show_in_rest" => true
    ]));
  }

  public function tamperMetaBoxes() {
    add_meta_box(
      "wplfb_metabox",
      "Form builder",
      function($post) {
        ?>
        <?php
        var_dump($post);
      },
      "wplfb-field",
      "advanced",
      "high",
      [$GLOBALS["post"]]
    );

    add_meta_box(
      "wplfb_buildarea",
      "Form builder",
      function() {
        echo "Hello!";
      },
      "wplf-form",
      "advanced",
      "high"
    );
  }

  public function registerRESTRoutes() {
    register_rest_route("wplfb", "/forms/form", [
      "methods" => "GET",
      "callback" => function (WP_REST_Request $request) {
        return $this->getForm($request);
      },
    ]);

    register_rest_route("wplfb", "/forms/form", [
      "methods" => "POST",
      "callback" => function (WP_REST_Request $request) {
        return $this->saveForm($request);
      },
    ]);
  }

  public function getForm(WP_REST_Request $request) {
    $form_id = 55; // Temp.
    if (is_null($form_id)) {
      return new WP_REST_Response([
        "error" => self::ERR_FORM_ID_EMPTY
      ]);
    }

    $p = get_post($form_id);

    return new WP_REST_Response([
      "post" => $p,
      "fields" => get_post_meta($p->ID, "wplfb_fields", true)
    ]);
  }

  public function saveForm(WP_REST_Request $request) {
    $form_id = 55; // Temp.

    // Do not check for null. Create a new one.
    // if (is_null($form_id)) {
      // return new WP_REST_Response([
        // "error" => self::ERR_FORM_ID_EMPTY
      // ]);
    // }

    $params = $request->get_body_params();
    $fields = $params["fields"];


    update_post_meta($form_id, "wplfb_fields", $fields); // Sanitize?
    $insert = wp_insert_post([
      "ID" => !is_null($form_id) ? $form_id : 0,
      "post_content" => $this->generateHTML($fields)
    ]);

    if (!is_wp_error($insert) && $insert !== 0) {
      return new WP_REST_Response([
        "success" => self::FORM_SAVED,
        "fields" => $fields
      ]);
    } else {
      return new WP_REST_Response([
        "error" => $insert
      ]);
    }

  }

  public function generateHTML($json = '') {
    $obj = json_decode($json);
    $shorts = ["br", "img"]; // Add all.
    $html = "";

    foreach ($obj as $key => $value) {
      $is_short = in_array($key, $shorts);
      $mayClose = $is_short ? "/" : "";
      $html .= "<$key $attrs $mayClose>";
      $html .= ""; // Add children here.
      $html .= !$is_short ? "</$key>" : "";
    }

    return $html;
  }
}
