<?php

namespace App\Http\Controllers;

use App\Models\OperationalMetric;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class MetricsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $expected = (string) config('operations.metrics_token');
        $provided = (string) (
            $request->bearerToken()
            ?: $request->header('X-SIGET-Metrics-Token')
        );

        abort_if(
            $expected === '' || !hash_equals($expected, $provided),
            403
        );

        $latest = OperationalMetric::query()
            ->orderBy('metric_key')
            ->orderByDesc('collected_at')
            ->get()
            ->unique('metric_key')
            ->values();

        $lines = [
            '# HELP siget_operational_metric Latest SIGET operational metric',
            '# TYPE siget_operational_metric gauge',
        ];

        foreach ($latest as $metric) {
            $name = 'siget_'.preg_replace(
                '/[^a-zA-Z0-9_:]/',
                '_',
                str_replace('.', '_', $metric->metric_key)
            );

            $lines[] = $name.' '.(float) $metric->metric_value;
        }

        return response(
            implode("\n", $lines)."\n",
            200,
            ['Content-Type' => 'text/plain; version=0.0.4']
        );
    }
}
