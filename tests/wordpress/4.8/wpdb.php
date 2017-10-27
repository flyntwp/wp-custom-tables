<?php

class wpdb
{
    public function prepare( $query, $args ) {
        if ( is_null( $query ) )
            return;
        // This is not meant to be foolproof -- but it will catch obviously incorrect usage.
        if ( strpos( $query, '%' ) === false ) {
        _doing_it_wrong( 'wpdb::prepare', sprintf( __( 'The query argument of %s must have a placeholder.' ), 'wpdb::prepare()' ), '3.9.0' );
        }
        $args = func_get_args();
        array_shift( $args );
        // If args were passed as an array (as in vsprintf), move them up
        if ( is_array( $args[0] ) && count( $args ) == 1 ) {
        $args = $args[0];
        }
        foreach ( $args as $arg ) {
        if ( ! is_scalar( $arg ) && ! is_null( $arg ) ) {
        _doing_it_wrong( 'wpdb::prepare', sprintf( 'Unsupported value type (%s).', gettype( $arg ) ), '4.8.2' );
        }
        }
        $query = str_replace( "'%s'", '%s', $query ); // in case someone mistakenly already singlequoted it
        $query = str_replace( '"%s"', '%s', $query ); // doublequote unquoting
        $query = preg_replace( '|(?<!%)%f|' , '%F', $query ); // Force floats to be locale unaware
        $query = preg_replace( '|(?<!%)%s|', "'%s'", $query ); // quote the strings, avoiding escaped strings like %%s
        $query = preg_replace( '/%(?:%|$|([^dsF]))/', '%%\\1', $query ); // escape any unescaped percents
        array_walk( $args, array( $this, 'escape_by_ref' ) );
        return @vsprintf( $query, $args );
    }

    public function escape_by_ref( &$string ) {
		if ( ! is_float( $string ) )
			$string = $this->_real_escape( $string );
	}

    function _real_escape( $string ) {
		if ( $this->dbh ) {
			if ( $this->use_mysqli ) {
				return mysqli_real_escape_string( $this->dbh, $string );
			} else {
				return mysql_real_escape_string( $string, $this->dbh );
			}
		}
		$class = get_class( $this );
		if ( function_exists( '__' ) ) {
			/* translators: %s: database access abstraction class, usually wpdb or a class extending wpdb */
			// _doing_it_wrong( $class, sprintf( __( '%s must set a database connection for use with escaping.' ), $class ), '3.6.0' );
		} else {
			// _doing_it_wrong( $class, sprintf( '%s must set a database connection for use with escaping.', $class ), '3.6.0' );
		}
		return addslashes( $string );
	}
}
