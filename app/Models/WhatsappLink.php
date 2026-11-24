<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * WhatsApp vinculado (por QR) de un usuario.
 *
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property string|null $phone
 * @property CarbonInterface|null $connected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WhatsappLink extends Model
{
    /** Nombre de la tabla. */
    public const TABLE = 'whatsapp_links';

    public const ID = 'id';

    public const USER_ID = 'user_id';

    public const STATUS = 'status';

    public const PHONE = 'phone';

    public const CONNECTED_AT = 'connected_at';

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_CONNECTING = 'connecting';

    public const STATUS_QR = 'qr';

    public const STATUS_CONNECTED = 'connected';

    protected $table = self::TABLE;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['connected_at' => 'datetime'];
    }
}
