<?php
/**
 * Translation Helper
 * 
 * Loads and manages translations for the application
 */

class Translation {
    private static $instance = null;
    private $translations = [];
    private $language = 'en'; // Default language
    
    private function __construct() {
        // Private constructor to prevent direct instantiation
    }
    
    /**
     * Get the singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Set the current language
     */
    public function setLanguage($lang) {
        $this->language = $lang;
        $this->loadTranslations();
    }
    
    /**
     * Get the current language
     */
    public function getLanguage() {
        return $this->language;
    }
    
    /**
     * Load translations for the current language
     */
    private function loadTranslations() {
        $translationFile = __DIR__ . "/../translations/{$this->language}.php";
        
        if (file_exists($translationFile)) {
            $this->translations = require $translationFile;
        } else {
            // Fallback to English if translation file doesn't exist
            if ($this->language !== 'en') {
                $this->language = 'en';
                $this->loadTranslations();
            }
        }
    }
    
    /**
     * Get a translated string
     */
    public function get($key, $replacements = []) {
        if (empty($this->translations)) {
            $this->loadTranslations();
        }
        
        $translation = $this->translations[$key] ?? $key;
        
        // Replace placeholders if any
        if (!empty($replacements)) {
            foreach ($replacements as $placeholder => $value) {
                $translation = str_replace(":$placeholder", $value, $translation);
            }
        }
        
        return $translation;
    }
    
    /**
     * Alias for get()
     */
    public static function trans($key, $replacements = []) {
        return self::getInstance()->get($key, $replacements);
    }
}

// Helper function for easier access to translations
function trans($key, $replacements = []) {
    return Translation::trans($key, $replacements);
}
