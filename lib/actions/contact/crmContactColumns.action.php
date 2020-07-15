<?php

class crmContactColumnsAction extends crmBackendViewAction {

    public function execute() {

        $all_columns = crmContact::getAllColumns();
        $contact_columns = crmContact::getCurrentContactColumns();
        $columns = self::getItems($all_columns, $contact_columns);
        $this->view->assign( array(
            'columns' => $columns
        ));
    }

    private function getItems($all_columns, $contact_columns) {

        $index = 0;
        $columns = array();

        foreach ($all_columns as $column_id => $column) {
            $column_name = htmlspecialchars($column["field"]->getName());

            if ($column["is_composite"]) {
                foreach ($column["sub_columns"] as $sub_column_id => $sub_column) {

                    $sub_column_name = htmlspecialchars($sub_column["field"]->getName());
                    $_full_column_id = $column_id . ':' . $sub_column_id;
                    $_text = $column_name . " &mdash; " . $sub_column_name;

                    $_off = 1;
                    $_sort = 0;
                    if (isset($contact_columns[$_full_column_id])) {
                        $_off = (int)ifset($contact_columns[$_full_column_id]['off']) >= 1 ? 1 : 0;
                        $_sort = !$_off ? (int)ifset($contact_columns[$_full_column_id]['sort']) : 0;
                    }
                    $columns[$index] = array(
                        "full_column_id" => $_full_column_id,
                        "text" => $_text,
                        "checked" => !$_off,
                        "sort" => $_sort,
                        "index" => $index++
                    );
                }
            } else {
                $_full_column_id = $column_id;
                $_text = $column_name;

                $_off = 1;
                $_sort = 0;
                if (isset($contact_columns[$column_id])) {
                    $_off = (int)ifset($contact_columns[$column_id]['off']) >= 1 ? 1 : 0;
                    $_sort = !$_off ? (int)ifset($contact_columns[$column_id]['sort']) : 0;
                }

                $columns[$index] = array(
                    "full_column_id" => $_full_column_id,
                    "text" => $_text,
                    "checked" => !$_off,
                    "sort" => $_sort,
                    "index" => $index++
                );
            }
        }

        usort($columns, wa_lambda('$a, $b', 'return $a["sort"] == $b["sort"] ? $a["index"] - $b["index"] : $a["sort"] - $b["sort"];'));

        return $columns;
    }
}
