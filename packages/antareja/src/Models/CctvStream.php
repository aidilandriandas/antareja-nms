<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use App\Models\Device;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sumber video satu kamera: lewat NVR (channel) atau langsung ke kamera.
 * URL RTSP (dengan kredensial) hanya disusun di server dan diserahkan ke gateway video internal.
 */
class CctvStream extends Model
{
    use Auditable;

    public const HEALTH = [
        'ok' => ['label' => 'Gambar normal', 'tone' => 'ok'],
        'dark' => ['label' => 'Gambar gelap', 'tone' => 'warn'],
        'blank' => ['label' => 'Layar polos / tertutup', 'tone' => 'crit'],
        'frozen' => ['label' => 'Video beku', 'tone' => 'crit'],
        'blur' => ['label' => 'Buram (lensa kotor / embun)', 'tone' => 'warn'],
        'moved' => ['label' => 'Arah berubah / terhalang', 'tone' => 'warn'],
        'error' => ['label' => 'Video tidak bisa diambil', 'tone' => 'crit'],
    ];

    protected $table = 'ant_cctv_streams';

    protected $fillable = ['device_id', 'nvr_device_id', 'host', 'rtsp_port', 'channel', 'rtsp_path', 'credential_id', 'enabled', 'health_check'];

    protected $casts = [
        'enabled' => 'boolean',
        'health_check' => 'boolean',
        'metrics' => 'array',
        'reference_metrics' => 'array',
        'reference_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'health_changed_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function nvr(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'nvr_device_id', 'device_id');
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(VideoCredential::class, 'credential_id');
    }

    /** Path RTSP: format Hikvision /Streaming/Channels/{channel}{01 utama | 02 sub} kecuali diisi manual. */
    public function path(bool $main = false): string
    {
        if ($this->rtsp_path) {
            return '/' . ltrim(str_replace('{stream}', $main ? '1' : '2', $this->rtsp_path), '/');
        }

        return '/Streaming/Channels/' . ($this->channel * 100 + ($main ? 1 : 2));
    }

    /** URL lengkap dengan kredensial — HANYA untuk gateway video internal, jangan pernah dikirim ke browser/log. */
    public function rtspUrl(bool $main = false): string
    {
        $c = $this->credential;

        return sprintf('rtsp://%s:%s@%s:%d%s', rawurlencode($c->username), rawurlencode($c->secret), $this->host, $this->rtsp_port, $this->path($main));
    }

    /** Nama stream di gateway; berubah bila sumber/kredensial berubah sehingga tidak memakai sumber lama. */
    public function gatewayName(bool $main = false): string
    {
        return 'cam' . $this->id . ($main ? 'm' : 's') . '-' . substr(hash('sha256', $this->rtspUrl($main) . '|' . config('app.key')), 0, 10);
    }

    public function healthMeta(): array
    {
        return self::HEALTH[$this->health] ?? ['label' => 'Belum dicek', 'tone' => 'unknown'];
    }

    public function auditType(): string
    {
        return 'cctv_stream';
    }

    public function auditLabel(): string
    {
        return 'Stream CCTV';
    }

    public function auditName(): string
    {
        return $this->device?->displayName() ?? "#{$this->id}";
    }
}
