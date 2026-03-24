<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;
    
    /**
     * The table associated with the model.
     */
    protected $table = 'users';
    
    /**
     * The primary key for the model.
     * Note: Your table has two id columns - bigint and uuid.
     * We'll use the bigint id as primary key (auto-incrementing)
     */
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    
    /**
     * The attributes that are mass assignable.
     * Only include columns that ACTUALLY exist in your table
     */
    protected $fillable = [
        // Primary identifiers
        'username',           // NOT NULL - character varying
        'name',              // NOT NULL - character varying
        'email',             // NOT NULL - character varying (the second email column)
        
        // Authentication
        'password',          // NOT NULL - character varying
        'remember_token',    // NULL - character varying
        
        // Roles and permissions
        'role',              // NOT NULL - character varying (the second role column with default)
        'previous_role',     // NULL - character varying
        'is_current_director', // NOT NULL - boolean with default false
        'is_super_admin',    // NULL - boolean
        'is_sso_user',       // NOT NULL - boolean with default false
        'is_anonymous',      // NOT NULL - boolean with default false
        
        // Timestamps
        'created_at',        // NULL - timestamp without time zone
        'updated_at',        // NULL - timestamp without time zone
        'deleted_at',        // NULL - timestamp with time zone
        
        // Email verification
        'email_verified_at', // NULL - timestamp without time zone
        'email_confirmed_at', // NULL - timestamp with time zone
        'confirmation_token', // NULL - character varying
        'confirmation_sent_at', // NULL - timestamp with time zone
        'email_change',      // NULL - character varying
        'email_change_token_new', // NULL - character varying
        'email_change_token_current', // NULL - character varying
        'email_change_sent_at', // NULL - timestamp with time zone
        'email_change_confirm_status', // NULL - smallint
        
        // Phone
        'phone',             // NULL - text
        'phone_confirmed_at', // NULL - timestamp with time zone
        'phone_change',      // NULL - text
        'phone_change_token', // NULL - character varying
        'phone_change_sent_at', // NULL - timestamp with time zone
        
        // Recovery and security
        'recovery_token',    // NULL - character varying
        'recovery_sent_at',  // NULL - timestamp with time zone
        'reauthentication_token', // NULL - character varying
        'reauthentication_sent_at', // NULL - timestamp with time zone
        
        // Metadata
        'aud',               // NULL - character varying
        'instance_id',       // NULL - uuid
        'raw_app_meta_data', // NULL - jsonb
        'raw_user_meta_data', // NULL - jsonb
        
        // Custom fields for your application
        'promoted_by',       // NULL - bigint
        'promoted_at',       // NULL - timestamp without time zone
        'appointed_by',      // NULL - bigint
        'appointed_at',      // NULL - timestamp without time zone
        'term_start',        // NULL - timestamp without time zone
        'term_end',          // NULL - timestamp without time zone
        
        // Additional auth fields
        'invited_at',        // NULL - timestamp with time zone
        'last_sign_in_at',   // NULL - timestamp with time zone
        'banned_until',      // NULL - timestamp with time zone
        'confirmed_at',      // NULL - timestamp with time zone
        
        // Note: There's also an 'encrypted_password' column, but we won't use it
        // as Laravel uses 'password' column
    ];
    
    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'confirmation_token',
        'recovery_token',
        'email_change_token_new',
        'email_change_token_current',
        'phone_change_token',
        'reauthentication_token',
        'encrypted_password', // If it exists, hide it
    ];
    
    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        // Timestamps
        'email_verified_at' => 'datetime',
        'email_confirmed_at' => 'datetime',
        'confirmation_sent_at' => 'datetime',
        'recovery_sent_at' => 'datetime',
        'email_change_sent_at' => 'datetime',
        'last_sign_in_at' => 'datetime',
        'phone_confirmed_at' => 'datetime',
        'phone_change_sent_at' => 'datetime',
        'banned_until' => 'datetime',
        'reauthentication_sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'invited_at' => 'datetime',
        'term_start' => 'datetime',
        'term_end' => 'datetime',
        'promoted_at' => 'datetime',
        'appointed_at' => 'datetime',
        
        // Booleans
        'is_current_director' => 'boolean',
        'is_super_admin' => 'boolean',
        'is_sso_user' => 'boolean',
        'is_anonymous' => 'boolean',
        
        // JSON
        'raw_app_meta_data' => 'json',
        'raw_user_meta_data' => 'json',
        
        // Other
        'email_change_confirm_status' => 'integer',
        'password' => 'hashed',
    ];
    
    // Role constants
    const ROLE_ADMIN = 'admin';
    const ROLE_COURSE_DIRECTOR = 'course_director';
    const ROLE_FACULTY = 'faculty';
    const ROLE_TRAINEE = 'trainee';
    
    // All available roles
    public static function getRoles(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_COURSE_DIRECTOR,
            self::ROLE_FACULTY,
            self::ROLE_TRAINEE,
        ];
    }
    
    // Check if user has admin-level access
    public function isAdminLevel(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_COURSE_DIRECTOR]);
    }
    
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
    
    public function isCourseDirector(): bool
    {
        return $this->role === self::ROLE_COURSE_DIRECTOR;
    }
    
    public function isFaculty(): bool
    {
        return $this->role === self::ROLE_FACULTY;
    }
    
    public function isTrainee(): bool
    {
        return $this->role === self::ROLE_TRAINEE;
    }
    
    // Get the current active Course Director
    public static function getCurrentCourseDirector()
    {
        return self::where('role', self::ROLE_COURSE_DIRECTOR)
            ->where('is_current_director', true)
            ->first();
    }
    
    // Get the admin who appointed this user
    public function appointedBy()
    {
        return $this->belongsTo(User::class, 'appointed_by');
    }
    
    // Get the user who promoted this user
    public function promotedBy()
    {
        return $this->belongsTo(User::class, 'promoted_by');
    }
}