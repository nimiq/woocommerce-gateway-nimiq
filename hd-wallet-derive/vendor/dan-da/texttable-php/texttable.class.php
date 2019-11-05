<?php

// be safe and sane.  use strictmode if available via composer.
$autoload_file = __DIR__ . '/vendor/autoload.php';
if( file_exists( $autoload_file )) {
    require_once($autoload_file);
    \strictmode\initializer::init();
}

/***
 * A class to print text in formatted tables.
 */
class texttable {

    /**
     * Formats a fixed-width text table, with borders.
     *
     * @param $rows  array of rows.  each row contains table cells.
     * @param $headertype  keys | firstrow | none/null 
     * @param $footertype  keys | lastrow | none/null
     * @param $empty_row_string  String to use when there is no data, or null.
     */
    static public function table( $rows, $headertype = 'keys', $footertype = 'none', $empty_row_string = 'No Data' ) {
        
        if( !@count( $rows ) ) {
            
            if( $empty_row_string !== null ) {
                $rows = [ [ $empty_row_string ] ];
            }
            else {
                return '';
            }
        }

        $header = $footer = null;
        if( $headertype == 'keys' ) {        
            $header = array_keys( static::obj_arr( $rows[0] ) );
        }
        else if( $headertype == 'firstrow' ) {
            $header = static::obj_arr( array_shift( $rows ) );
        }
        if( $footertype == 'keys' && count( $rows ) ) {
            $footer = array_keys( static::obj_arr( $rows[count($rows) - 1] ) );
        }
        else if( $footertype == 'lastrow' && count( $rows ) ) {
            $footer = static::obj_arr( array_pop( $rows ) );
        }
        
        $col_widths = array();
        
        if( $header ) {
            static::calc_row_col_widths( $col_widths, $header );
        }
        if( $footer ) {
            static::calc_row_col_widths( $col_widths, $footer );
        }
        foreach( $rows as $row ) {
            $row = static::obj_arr( $row );
            static::calc_row_col_widths( $col_widths, $row );
        }
        
        $buf = '';
        $buf .= static::print_divider_row( $col_widths, 'top' );
        if( $header ) {        
            $buf .= static::print_header($col_widths, $header );
        }
        foreach( $rows as $row ) {
            $row = static::obj_arr( $row );
            $buf .= static::print_row( $col_widths, $row );
        }
        $buf .= static::print_divider_row( $col_widths, 'bottom' );
        if( $footer ) {
            $buf .= static::print_footer($col_widths, $footer );
        }
        
        return $buf;
    }
    
    static protected function print_footer($col_widths, $footer) {
        $buf  = static::print_row( $col_widths, $footer );
        $buf .= static::print_divider_row( $col_widths, 'footer' );
        return $buf;
    }

    static protected function print_header($col_widths, $header) {
        $buf  = static::print_row( $col_widths, $header );
        $buf .= static::print_divider_row( $col_widths, 'header' );
        return $buf;
    }

    static protected function print_divider_row( $col_widths, $position ) {
        $buf = '+';
        foreach( $col_widths as $width ) {
            $buf .= '-' . str_pad( '-', $width, '-' ) . "-+";
        }
        $buf .= "\n";
        return $buf;
    }
    
    static protected function print_row( $col_widths, $row ) {
        $buf = '|';
        $idx = 0;
        foreach( $row as $val ) {
            $pad_type = is_numeric( $val ) ? STR_PAD_LEFT : STR_PAD_RIGHT;
            $buf .= ' ' . str_pad( $val, $col_widths[$idx], ' ', $pad_type ) . " |";
            $idx ++;
        }
        return $buf . "\n";
    }

    static protected function calc_row_col_widths( &$col_widths, $row ) {
        $idx = 0;
        foreach( $row as $val ) {
            $len = strlen( $val );
            if( $len > @$col_widths[$idx] ) {
                $col_widths[$idx] = $len;
            }
            $idx ++;
        }
    }
    
    static protected function obj_arr( $t ) {
       return is_object( $t ) ? get_object_vars( $t ) : $t;
    }
}
