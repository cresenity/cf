<?php

class CAjax_Engine_SelectSearch_Processor_Query extends CAjax_Engine_SelectSearch_Processor {
    public function process() {
        $q = $this->query();

        $valueCallbackFunction = carr::get($this->data, 'valueCallback', null);
        $keyField = $this->keyField();

        $searchField = $this->searchField();

        $db = CDatabase::instance();

        $term = '';
        $limit = '';
        $page = '';

        $callback = $this->callback();
        $term = $this->searchTerm();
        $limit = $this->pageSize();
        $page = $this->page();

        $base_q = $q;
        $posOrderBy = strpos(strtolower($base_q), 'order by', strpos(strtolower($base_q), 'from'));

        $posLastBracket = strrpos(strtolower($base_q), ')');

        $tempOrderBy = '';
        if ($posOrderBy > $posLastBracket) {
            if ($posOrderBy !== false) {
                $tempOrderBy = substr($base_q, $posOrderBy, strlen($base_q) - $posOrderBy);
                $base_q = substr($base_q, 0, $posOrderBy);
            }
        }

        $total = cdbutils::get_row_count_from_base_query($q);

        /* Paging */
        $sLimit = '';
        if (strlen($limit) > 0) {
            if (strlen($page) > 0) {
                $sLimit = 'LIMIT ' . ((intval($page) - 1) * 10) . ', ' . intval($limit);
            } else {
                $sLimit = 'LIMIT ' . intval($limit);
            }
        }

        /* Ordering */
        $sOrder = '';
        /*
          if ( isset( $_GET['iSortCol_0'] ) ) {
          $sOrder = "ORDER BY  ";
          for ( $i=0 ; $i<intval( $_GET['iSortingCols'] ) ; $i++ ) {
          if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" ) {
          $sOrder .= "`".$columns[ intval( $_GET['iSortCol_'.$i] ) ]->fieldname."` ".mysql_real_escape_string( $_GET['sSortDir_'.$i] ) .", ";
          }
          }

          $sOrder = substr_replace( $sOrder, "", -2 );
          if ( $sOrder == "ORDER BY" ) {
          $sOrder = "";
          }
          }

         */

        $sWhere = '';
        if (strlen($term) > 0 && (!empty($searchField))) {
            $sWhere = 'WHERE (';
            if (is_array($searchField)) {
                foreach ($searchField as $f) {
                    $sWhere .= '`' . $f . "` LIKE '%" . $db->escapeLike($term) . "%' OR ";
                }
            } else {
                $sWhere .= '`' . $searchField . "` LIKE '%" . $db->escapeLike($term) . "%' OR ";
            }

            $sWhere = substr_replace($sWhere, '', -3);
            $sWhere .= ')';

            //order
            if (is_array($searchField)) {
                foreach ($searchField as $f) {
                    if (strlen($sOrder) > 0) {
                        $sOrder .= ',';
                    }
                    $sOrder .= '`' . $f . '` = ' . $db->escape($term) . ' DESC';
                }
            }
        }

        if (strlen($sOrder) > 0) {
            $sOrder = ' ORDER BY ' . $sOrder;
            $tempOrderBy = '';
        }

        if (strlen($tempOrderBy) > 0) {
            $sub = explode(',', substr($tempOrderBy, 9));
            $tempOrderBy = '';
            foreach ($sub as $val) {
                $kata = explode('.', $val);
                if (isset($kata[1])) {
                    $tempOrderBy .= ', ' . $kata[1];
                } else {
                    $tempOrderBy .= ', ' . $kata[0];
                }
            }
            $tempOrderBy = substr($tempOrderBy, 2);
            $tempOrderBy = 'ORDER BY ' . $tempOrderBy;
        }

        $qfilter = 'select * from (' . $base_q . ') as a ' . $sWhere . ' ' . $sOrder;
        $total = cdbutils::get_row_count_from_base_query($qfilter);

        $qfilter .= ' ' . $tempOrderBy . ' ' . $sLimit;

        $r = $db->query($qfilter);

        $result = $r->result(false);
        $data = [];
        foreach ($r as $row) {
            $p = [];
            foreach ($row as $k => $v) {
                if ($valueCallbackFunction != null && is_callable($valueCallbackFunction)) {
                    $v = call_user_func($valueCallbackFunction, $row, $k, $v);
                }
                $p[$k] = ($v == null) ? '' : $v;
            }
            if (strlen($keyField) > 0 && !isset($p['id'])) {
                $p['id'] = carr::get($row, $keyField);
            }

            $formatResult = $this->formatResult();
            if ($formatResult instanceof \Opis\Closure\SerializableClosure) {
                $formatResult = $formatResult->__invoke($row);
                $p['cappFormatResult'] = $formatResult;
                $p['cappFormatResultIsHtml'] = c::isHtml($formatResult);
            }
            $formatSelection = $this->formatSelection();
            if ($formatSelection instanceof \Opis\Closure\SerializableClosure) {
                $formatSelection = $formatSelection->__invoke($row);
                $p['cappFormatSelection'] = $formatSelection;
                $p['cappFormatSelectionIsHtml'] = c::isHtml($formatSelection);
            }
            //$p["id"]=$row["item_id"];
            $data[] = $p;
        }
        $result = [];
        $result['data'] = $data;
        $result['total'] = $total;

        return c::response()->jsonp($callback, $result);
    }
}
