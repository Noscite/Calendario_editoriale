<?php

declare(strict_types=1);

namespace App\Domain\Campaign\Jobs;

use App\Domain\Campaign\Models\CampaignAttachment;
use App\Domain\Campaign\Services\AttachmentTextExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ExtractAttachmentTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries   = 2;

    public function __construct(public readonly int $attachmentId) {}

    public function handle(AttachmentTextExtractor $extractor): void
    {
        $attachment = CampaignAttachment::find($this->attachmentId);

        if (! $attachment) {
            return;
        }

        $extractor->extract($attachment);
    }
}
