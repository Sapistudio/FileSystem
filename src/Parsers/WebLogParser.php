<?php
namespace SapiStudio\FileSystem\Parsers;
/**
 * @class Handler
 * @package SAPI Framework
 * @author Laurentiu Sandu
 * @copyright SAPI Studio
 * @version 5.rb 2012
 * @path libraries/classes/Nginx/WebLogParser.php
 * @access public
 * fork after https://github.com/globalmac/KnuckleLog.git
 */
class WebLogParser
{
    protected static $defaultFormat = '%h %l %u %t "%r" %>s %O "%{Referer}i" \"%{User-Agent}i"';
    protected $pcreFormat;
    protected $fileLogHandler  = null;
    protected $parsedBuffer  = [];
    protected $patterns = ['%%' => '(?P<percent>\%)', '%a' => '(?P<remoteIp>)', '%A' =>
        '(?P<localIp>)', '%h' => '(?P<host>[a-zA-Z0-9\-\._:]+)', '%l' =>
        '(?P<logname>(?:-|[\w-]+))', '%m' =>
        '(?P<requestMethod>OPTIONS|GET|HEAD|POST|PUT|DELETE|TRACE|CONNECT|PATCH|PROPFIND)',
        '%p' => '(?P<port>\d+)', '%r' =>
        '(?P<request>(?:(?:[A-Z]+) .+? HTTP/1.(?:0|1))|-|)', '%t' => '\[(?P<time>\d{2}/(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/\d{4}:\d{2}:\d{2}:\d{2} (?:-|\+)\d{4})\]',
        '%u' => '(?P<user>(?:-|[\w-]+))', '%U' => '(?P<URL>.+?)', '%v' =>
        '(?P<serverName>([a-zA-Z0-9]+)([a-z0-9.-]*))', '%V' =>
        '(?P<canonicalServerName>([a-zA-Z0-9]+)([a-z0-9.-]*))', '%>s' => '(?P<status>\d{3}|-)',
        '%b' => '(?P<responseBytes>(\d+|-))', '%T' => '(?P<requestTime>(\d+\.?\d*))',
        '%O' => '(?P<sentBytes>[0-9]+)', '%I' => '(?P<receivedBytes>[0-9]+)', '\%\{(?P<name>[a-zA-Z]+)(?P<name2>[-]?)(?P<name3>[a-zA-Z]+)\}i' =>
        '(?P<Header\\1\\3>.*?)', '%D' => '(?P<timeServeRequest>[0-9]+)', ];

    /**
     * WebLogParser::initWebLogFile()
     * 
     * @return
     */
    public static function initWebLogFile($fileName = null){
        return new static($fileName);
    }
    
    /**
     * WebLogParser::getDefaultFormat()
     * 
     * @return
     */
    public static function getDefaultFormat()
    {
        return self::$defaultFormat;
    }
    
    /**
     * WebLogParser::__construct()
     * 
     * @return
     */
    public function __construct($fileName = null)
    {
        if (!($this->fileLogHandler = fopen($fileName, "rb"))) {
            throw new \Exception("Cannot open the file");
        }
        // Set IPv4 & IPv6 recognition patterns
        $ipPatterns = implode('|', [
            'ipv4' => '(((25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9])\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9]))',
            'ipv6full' => '([0-9A-Fa-f]{1,4}(:[0-9A-Fa-f]{1,4}){7})', // 1:1:1:1:1:1:1:1
            'ipv6null' => '(::)',
            'ipv6leading' => '(:(:[0-9A-Fa-f]{1,4}){1,7})', // ::1:1:1:1:1:1:1
            'ipv6mid' => '(([0-9A-Fa-f]{1,4}:){1,6}(:[0-9A-Fa-f]{1,4}){1,6})', // 1:1:1::1:1:1
            'ipv6trailing' => '(([0-9A-Fa-f]{1,4}:){1,7}:)', // 1:1:1:1:1:1:1::
        ]);
        $this->patterns['%a'] = '(?P<remoteIp>' . $ipPatterns . ')';
        $this->patterns['%A'] = '(?P<localIp>' . $ipPatterns . ')';
        $this->setFormat(self::getDefaultFormat());
    }
    
    /**
     * WebLogParser::addPattern()
     * 
     * @return
     */
    public function addPattern($placeholder, $pattern)
    {
        $this->patterns[$placeholder] = $pattern;
    }
    
    /**
     * WebLogParser::setFormat()
     * 
     * @return
     */
    public function setFormat($format)
    {
        $expr = "#^{$format}$#";
        foreach ($this->patterns as $pattern => $replace) {
            $expr = preg_replace("/{$pattern}/", $replace, $expr);
        }
        $this->pcreFormat = $expr;
    }
    
    /**
     * WebLogParser::readFileLog()
     * 
     * @return
     */
    public function readFileLog()
    {
        if (!$this->fileLogHandler) {
            throw new Exception("Invalid file pointer");
        }
        while(!feof($this->fileLogHandler)){
            $parsedLine     = $this->parseLogLine(fgets($this->fileLogHandler));
            if(!$parsedLine)
                continue;
            $this->parsedBuffer[] = $parsedLine;
        }
        $this->parsedBuffer = array_filter($this->parsedBuffer);
    }
    
    /**
     * WebLogParser::parseLogLine()
     * 
     * @return
     */
    public function parseLogLine($line)
    {
        if (!preg_match($this->pcreFormat, $line, $matches)) {
            return false;
        }
        $entry = new \stdClass();
        foreach(array_filter(array_keys($matches), 'is_string') as $key) {
            if ('time' === $key && true !== $stamp = strtotime($matches[$key]))
                $entry->stamp = $stamp;
            $entry->{$key} = $matches[$key];
        }
        return $entry;
    }
    
    /**
     * WebLogParser::getPCRE()
     * 
     * @return
     */
    public function getPCRE()
    {
        return (string )$this->pcreFormat;
    }
    
    /**
     * WebLogParser::parseLogFile()
     * 
     * @return
     */
    public function parseLogFile()
    {
        $this->readFileLog();
        return (!$this->parsedBuffer) ? false : $this->parsedBuffer;
    }
}
