<?php

namespace App\Models\Custom;

use App\Exceptions\CronJobSaveFailed;
use App\Models\CronJob;
use App\Models\Custom\Enums\CronJobEmailMe;
use App\Models\Custom\Enums\CronJobStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileNotFoundException;

class Parser
{
    /**
     * @throws FileNotFoundException
     */
    public function __construct(
        private readonly string $filename
    )
    {
        if (!Storage::exists($filename)) {
            throw new FileNotFoundException(Storage::path($filename));
        }
    }

    /**
     * @throws CronJobSaveFailed
     */
    public function processCronJobs(): Collection
    {
        $data = collect($this->readCSV());

        return $data
            ->skip(1)
            ->map(static function ($iJob) {
                if (count($iJob) !== 10) {
                    return false;
                }
                $job = new CronJob();

                $job->cron_job_id = (int)$iJob[0];
                $job->name = $iJob[1];
                $job->expression = $iJob[2];
                $job->url = $iJob[3];
                $job->email_me = match ($iJob[4]) {
                    'never' => CronJobEmailMe::never,
                    'if execution fail' => CronJobEmailMe::if_execution_fail,
                };
                $job->log = $iJob[5];
                $job->post = $iJob[6];
                $job->status = match ($iJob[7]) {
                    'enabled' => CronJobStatus::enabled,
                    'disabled' => CronJobStatus::disabled,
                };
                $job->execution_time = $iJob[8];

                if (!$job->save()) {
                    throw new CronJobSaveFailed();
                }

                return $job->fresh();
            })
            ->reject(static function ($value) {
                return $value === false;
            });
    }

    private function readCSV(): array
    {
        $line_of_text = [];
        $file_handle = fopen(Storage::path($this->filename), 'rb');

        while (!feof($file_handle)) {
            $line_of_text[] = fgetcsv($file_handle, 0, ',');
        }

        fclose($file_handle);
        return $line_of_text;
    }
}
