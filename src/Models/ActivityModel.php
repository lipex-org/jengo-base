<?php

declare(strict_types=1);

namespace Jengo\Base\Models;

use CodeIgniter\Model;

class ActivityModel extends Model
{
    protected $table            = 'activities';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'url',
        'ip_address',
        'user_agent',
        'created_at'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = ''; // No updated_at for logs

    /**
     * Log an activity
     */
    public function log(array $data): void
    {
        $data['url']        = current_url();
        $data['ip_address'] = service('request')->getIPAddress();
        $data['user_agent'] = service('request')->getUserAgent()->getAgentString();
        $data['user_id']    = $data['user_id'] ?? (function_exists('user_id') ? user_id() : null);

        $this->insert($data);
    }
}
