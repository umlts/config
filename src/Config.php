<?php

declare( strict_types = 1 );
namespace Umlts\Config;

/**
 * Loads configuration files
 * 
 * The configuration files loaded can be changed via command line
 * arguments:
 * 
 * @example php config-test.php --config:file=/tmp/test.config.json
 * @example php config-test.php --config:file=/tmp/test.config.json --config:file=/tmp/test2.config.ini
 * @example php config-test.php --config:file=http://localhost/test.config.json
 */
class Config {
    
    const OPT_NAMESPACE = 'config';
    
    /**
     * @var string $basedir
     */
    private $basedir = './';
    
    /**
     * Array holding all the configuration information.
     * 
     * @var array $config
     */
    private $config = array();
    
    /**
     * The namespace in use
     * 
     * @var array $ns
     */
    private $ns = array();
    
    /**
     * @var array $base_config_file
     */ 
    private $base_config_files = [
        'config/config.json',
        'config/config.yml',
        'config/config.ini',
    ];
    
    /**
     * Constructs the objects
     * 
     * @param string $basedir
     *   Directory to look for the default configuration files
     * @param bool $ignore_default
     *   Do not look for the default configuration files
     */
    public function __construct( string $basedir = './', bool $ignore_default = FALSE ) {
        $this->basedir = substr( $basedir, -1 ) == '/' ? $basedir :  $basedir . '/';
        
        if ( $ignore_default === FALSE ) {
            $this->optsIgnoreDefaultConfig();
            $this->loadDefaultFiles();
        }
        
        $this->optsConfigFiles();
    }
    
    
    /**
     * Reads the CLI arguments and loads the given config files.
     * 
     * @return Config
     *   Returns this object
     */
    private function optsConfigFiles() : Config {
        $opt = getopt( '', [ self::OPT_NAMESPACE . ':file:' ] );
        
        foreach ( $opt as $option ) {
            if ( is_array( $option ) ) {
                foreach ( $option as $o ) {
                    $this->load( $o );
                }
            } else {
                $this->load( $option );
            }
        }
        
        return $this;
    }
    
    /**
     * Read the CLI arguments and removes the default config files
     * so they won't be loaded.
     * 
     * @example php config-test.php --config:ignore-default --config:file=/tmp/test.config.json
     * 
     * @return Config
     *   Returns this object
     */
    private function optsIgnoreDefaultConfig()  : Config {
        $opt = getopt( '', [
            self::OPT_NAMESPACE . ':ignore-default',
            self:: OPT_NAMESPACE . ':ignore-default'
        ] );
        if ( !empty( $opt ) ) {
            $this->base_config_files = [];
        }
        return $this;
    }
    
    /**
     * Merges $data into the existing configuration. Overwrites settings
     * from $this->config with the values from $data if the settings
     * already exists.
     * 
     * @return Config
     *   Returns this object 
     */
    private function merge( array $data ) : Config {
        $this->config = array_replace_recursive( $this->config, $data );
        return $this;
    }
    
    /**
     * Loads a configuration file.
     * 
     * @param string $file
     *   Path to file. Maybe a stream, too.
     * @param string $format
     *   Sets the config file format. Mehtod does not try to guess
     *   the format on its own if given.
     * 
     * @return Config
     *   Returns this object 
     */
    public function load( string $file, string $format = '' ) : Config {
        
        try {
            $content = @file_get_contents( $file );
        } catch ( \Exception $e ) {
            throw new \InvalidArgumentException( 'Cannot open "' . $file . '".' );
        }
        
        if ( $content === FALSE ) {
            throw new \InvalidArgumentException( 'Cannot open "' . $file . '".' );
        }
        
        if ( empty( $format ) ) {
            $format = $this->guessFormat( $file );
        }
        
        switch ( $format ) {
            case 'json':
                $data = json_decode( $content, TRUE );
                if ( $data == NULL && json_last_error() != JSON_ERROR_NONE ) {
                    throw new \RuntimeException( 'Error parsing JSON: ' . json_last_error_msg() );
                }
                $data = (array) $data;
                break;
            case 'yml':
            case 'yaml':
                $data = yaml_parse( $content );
                if ( $data === FALSE ) {
                    throw new \RuntimeException( 'Error parsing YAML.' );
                }
                break;
            case 'ini':
                $data = parse_ini_string( $content, TRUE );
                if ( $data === FALSE ) {
                    throw new \RuntimeException( 'Error parsing Ini file.' );
                }
                break;
            default:
                throw new \InvalidArgumentException( 'Cannot guess the file format or the format does not exist.' );
                break;
        }
        
        if ( !empty( $data ) ) { $this->merge( $data ); }
        
        return $this;
    }
    
    /**
     * Loads the default configuration files.
     * 
     * @return Config
     *   Returns this object 
     */
    private function loadDefaultFiles() : Config {
        if ( empty( $this->base_config_files ) ) { return $this; }
        foreach( $this->base_config_files as $file ) {
            try {
                $this->load( $this->basedir . $file );
            } catch ( \InvalidArgumentException $e ) {
                // Ignore errors opening the default config files
                // Does not touch exceptions for parsing errors, though.
            }
        }
        return $this;
    }
    
    /**
     * Guesses the config file format.
     * 
     * @return string
     *   Returns the file extension in lowercase. Returns an empty string
     *   if the extension could not be determined.
     */
    private function guessFormat( string $file ) : string {
        
        if ( preg_match( '/\.(json|yml|yaml|ini)($|\?|\#)/i', $file, $result ) ) {
            return strtolower( $result[1] );
        }
        
        return '';
    }
    
    /**
     * Sets the active namespace. All key in get calls will be prepended
     * by the namespace.
     * 
     * @param $ns
     *   The namespace. Seperated by '/'.
     * 
     * @return Config
     *   Returns this object.
     */
    public function setNamespace( string $ns ) : Config {
        if ( empty( $ns ) ) {
            $this->ns = [];
            return $this;
        }
        $this->ns = explode( '/', $ns );
        return $this;
    }
    
    /**
     * @return string
     */
    public function getNamespace() : string {
        return implode( '/', $this->ns );
    }
    
    /**
     * @return array
     */
    public function getNamespaceArray() : array {
        return $this->ns;
    }
    
    /**
     * Prepends the key with the active namespace.
     * 
     * @param string $key
     * 
     * @return array
     *   Returns the key array with namespace
     */
    private function prependKey( string $key ) : array {
        if ( empty( $key ) ) { return $this->getNamespaceArray(); }
        $key_array = explode( '/', $key );
        return array_merge( $this->getNamespaceArray(), $key_array );
    }
    
    /**
     * Gets an configuration setting.
     * 
     * @param string $key
     *   The key for the setting. Seperated by '/'.
     * @param mixed $default
     *   The default value if key is not set.
     * 
     * $return mixed
     *   Returns the value
     */
    public function get( string $key = '', $default = NULL ) {
        
        $key_array = $this->prependKey( $key );
        
        $value = $this->config;
        
        foreach ( $key_array as $k ) {
            
            if ( !isset( $value[ $k ] ) ) {
                if ( isset( $default ) ) {
                    return $default;
                } else {
                    throw new \InvalidArgumentException( 'Setting with the key ' . implode( '/', $key_array ) . ' does not exist.' );
                }
            }
            
            $value = $value[ $k ];
        }
        return $value;
    }
    
    /**
     * Sets a configuration setting.
     * 
     * @param string $key
     *   The key for the setting. Seperated by '/'.
     * @param mixed $value
     *   The value to set
     * 
     * $return Config
     *   Returns this object
     */
    public function set( string $key, $value ) : Config {
        
        $new = $value;
        
        $key_array = array_reverse( $this->prependKey( $key ) );
        
        foreach ( $key_array as $k ) { $new = [ $k => $new ]; }
        
        $this->merge( $new );
        
        return $this;
    }
    
    /**
     * Magic __toString function.
     * @return string
     *   print_r output for the $this->config
     */
    public function __toString() {
        $config = $this->get();
        return print_r( $config, TRUE );
    }
}
