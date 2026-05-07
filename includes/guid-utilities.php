<?php
/* GUID GENERATION AND VALIDATION UTILITIES
 
This file provides GUID generation, validation, and conversion utilities using MySQL native functions for enhanced security and backward compatibility during migration. */

require_once __DIR__ . '/dbh.inc.php';

//Interface for GUID generation and validation operations
interface GuidGeneratorInterface
{
    public function generateGuid(): string;
    public function validateGuid(string $guid): bool;
    public function guidToBytes(string $guid): string;
    public function bytesToGuid(string $bytes): string;
}

/* GUID Generator implementation using MySQL native UUID functions

Uses MySQL's UUID() function for generation and uuid_to_bin()/bin_to_uuid() for conversion between string and binary formats for optimal storage. */
class GuidGenerator implements GuidGeneratorInterface
{
    private mysqli $conn;
    
    public function __construct()
    {
        $this->conn = getDbConnection();
    }
    
    //Generate a new GUID using MySQL's native UUID function
    public function generateGuid(): string
    {
        $result = mysqli_query($this->conn, "SELECT uuid() as guid");
        
        if (!$result) {
            throw new RuntimeException('Failed to generate GUID: ' . mysqli_error($this->conn));
        }
        
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        
        if (!$row || !isset($row['guid'])) {
            throw new RuntimeException('Invalid GUID generation result');
        }
        
        return $row['guid'];
    }
    
    //Validate GUID format compliance with UUID v4 standard
    public function validateGuid(string $guid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $guid) === 1;
    }
    
    //Convert GUID string to binary format for database storage
    public function guidToBytes(string $guid): string
    {
        if (!$this->validateGuid($guid)) {
            throw new InvalidArgumentException("Invalid GUID format: $guid");
        }
        
        $stmt = $this->conn->prepare("SELECT uuid_to_bin(?, true) as bytes");
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare GUID conversion statement: ' . mysqli_error($this->conn));
        }
        
        $stmt->bind_param("s", $guid);
        
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to execute GUID conversion: ' . mysqli_error($this->conn));
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if (!$row || !isset($row['bytes'])) {
            throw new RuntimeException('Invalid GUID conversion result');
        }
        
        return $row['bytes'];
    }
    
    //Convert binary GUID to string format for display/API responses
    public function bytesToGuid(string $bytes): string
    {
        if (strlen($bytes) !== 16) {
            throw new InvalidArgumentException('Invalid binary GUID length: expected 16 bytes, got ' . strlen($bytes));
        }
        
        $stmt = $this->conn->prepare("SELECT bin_to_uuid(?, true) as guid");
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare binary conversion statement: ' . mysqli_error($this->conn));
        }
        
        $stmt->bind_param("s", $bytes);
        
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to execute binary conversion: ' . mysqli_error($this->conn));
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if (!$row || !isset($row['guid'])) {
            throw new RuntimeException('Invalid binary conversion result');
        }
        
        return $row['guid'];
    }
}





//GUID related exception classes for better error handling
class GuidValidationException extends InvalidArgumentException
{
    private ?string $context;
    
    public function __construct(string $invalidGuid, ?string $context = null, int $code = 0, ?Throwable $previous = null)
    {
        $this->context = $context;
        
        $message = "Invalid GUID format: $invalidGuid";
        if ($context) {
            $message .= " in context: $context";
        }
        
        parent::__construct($message, $code, $previous);
    }
    
    public function getContext(): ?string
    {
        return $this->context;
    }
}

class GuidGenerationException extends RuntimeException
{
    public function __construct(string $message = "GUID generation failed", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class IdMappingException extends RuntimeException
{
    public function __construct(string $message = "ID mapping operation failed", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

//Get a singleton instance of GuidGenerator
function getGuidGenerator(): GuidGenerator
{
    static $instance = null;
    if ($instance === null) {
        $instance = new GuidGenerator();
    }
    return $instance;
}



//Generate a new GUID (can be used for users, groups, or files)
function generateGuid(): string
{
    try {
        return getGuidGenerator()->generateGuid();
    } catch (RuntimeException $e) {
        throw new GuidGenerationException("Failed to generate GUID: " . $e->getMessage(), 0, $e);
    }
}



//Validate GUID format
function validateGuid(string $guid): bool
{
    return getGuidGenerator()->validateGuid($guid);
}

//Alias for validateGuid for consistency inside the code
function isValidGuid(string $guid): bool
{
    return validateGuid($guid);
}

//Convert GUID to binary format
function guidToBytes(string $guid): string
{
    try {
        return getGuidGenerator()->guidToBytes($guid);
    } catch (InvalidArgumentException $e) {
        throw new GuidValidationException($guid, 'guidToBytes conversion', 0, $e);
    }
}