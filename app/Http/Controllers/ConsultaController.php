<?php

namespace App\Http\Controllers;

use App\Models\Consulta;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ConsultaController extends Controller
{
    public function index()
    {
        $lotes = Consulta::select('lote')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados")
            ->selectRaw("SUM(CASE WHEN estado = 'error' THEN 1 ELSE 0 END) as errores")
            ->selectRaw('MIN(created_at) as fecha')
            ->groupBy('lote')
            ->orderByDesc('fecha')
            ->get();

        return view('consultas.index', compact('lotes'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'max:2048'],
        ]);

        $file = $request->file('csv_file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, ['csv', 'txt', 'xlsx', 'xls'])) {
            return response()->json(['success' => false, 'message' => 'Formato no soportado. Use CSV, TXT o XLSX.'], 422);
        }

        $cedulas = [];

        if (in_array($extension, ['xlsx', 'xls'])) {
            // Parse Excel file
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();

            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(true);

                foreach ($cellIterator as $cell) {
                    $value = trim((string) $cell->getValue());
                    if (preg_match('/^\d+$/', $value)) {
                        $cedulas[] = $value;
                    }
                    break; // Only first column
                }
            }

            // Remove header if first value is not numeric
            if (!empty($cedulas) && !is_numeric(str_replace(['-', '.'], '', $cedulas[0]))) {
                array_shift($cedulas);
            }
        } else {
            // Parse CSV/TXT file
            $content = file_get_contents($file->getRealPath());
            $lines = array_filter(array_map('trim', explode("\n", $content)));

            // Remove header if it looks like one
            if (!empty($lines) && !is_numeric(str_replace(['-', '.'], '', $lines[0]))) {
                array_shift($lines);
            }

            $cedulas = array_filter($lines, fn($l) => preg_match('/^\d+$/', $l));
        }

        $cedulas = array_values(array_unique($cedulas));

        if (empty($cedulas)) {
            return response()->json(['success' => false, 'message' => 'No se encontraron cédulas válidas en el archivo.'], 422);
        }

        $lote = date('Ymd_His') . '_' . Str::random(6);

        $records = [];
        foreach ($cedulas as $cedula) {
            $records[] = [
                'lote' => $lote,
                'user_id' => auth()->id(),
                'tipo_documento' => 'CC',
                'numero_documento' => $cedula,
                'estado' => 'pendiente',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Consulta::insert($records);

        return response()->json([
            'success' => true,
            'lote' => $lote,
            'total' => count($cedulas),
            'cedulas' => $cedulas,
        ]);
    }

    public function saveResult(Request $request)
    {
        $request->validate([
            'lote' => ['required', 'string'],
            'numero_documento' => ['required', 'string'],
            'estado' => ['required', 'in:completado,error'],
        ]);

        $consulta = Consulta::where('lote', $request->lote)
            ->where('numero_documento', $request->numero_documento)
            ->first();

        if (!$consulta) {
            return response()->json(['success' => false, 'message' => 'Consulta no encontrada.'], 404);
        }

        $updateData = ['estado' => $request->estado];

        if ($request->estado === 'completado' && $request->has('data')) {
            $data = $request->data;
            $updateData = array_merge($updateData, [
                'primer_nombre' => $data['primer_nombre'] ?? null,
                'segundo_nombre' => $data['segundo_nombre'] ?? null,
                'primer_apellido' => $data['primer_apellido'] ?? null,
                'segundo_apellido' => $data['segundo_apellido'] ?? null,
                'sexo' => $data['sexo'] ?? null,
                'celular' => $data['celular'] ?? null,
                'telefono1' => $data['telefono1'] ?? null,
                'telefono2' => $data['telefono2'] ?? null,
                'correo_electronico' => $data['correo_electronico'] ?? null,
                'tipo_afiliado' => $data['tipo_afiliado'] ?? null,
                'regimen' => $data['regimen'] ?? null,
                'categoria' => $data['categoria'] ?? null,
                'ips_primaria' => $data['ips_primaria'] ?? null,
                'departamento' => $data['departamento'] ?? null,
                'municipio' => $data['municipio'] ?? null,
                'respuesta_afiliado' => $data['respuesta_afiliado'] ?? null,
                'respuesta_paciente' => $data['respuesta_paciente'] ?? null,
            ]);
        }

        if ($request->estado === 'error') {
            $updateData['error'] = $request->error_message ?? 'Error desconocido';
        }

        $consulta->update($updateData);

        return response()->json(['success' => true]);
    }

    public function search(string $cedula)
    {
        $consultas = Consulta::where('numero_documento', $cedula)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'consultas' => $consultas,
        ]);
    }

    public function loteStatus(string $lote)
    {
        $consultas = Consulta::where('lote', $lote)->get();

        if ($consultas->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Lote no encontrado.'], 404);
        }

        return response()->json([
            'success' => true,
            'total' => $consultas->count(),
            'completados' => $consultas->where('estado', 'completado')->count(),
            'errores' => $consultas->where('estado', 'error')->count(),
            'pendientes' => $consultas->where('estado', 'pendiente')->count(),
            'consultas' => $consultas,
        ]);
    }

    public function export(string $lote)
    {
        $consultas = Consulta::where('lote', $lote)
            ->where('estado', 'completado')
            ->get();

        if ($consultas->isEmpty()) {
            return back()->with('error', 'No hay resultados para exportar.');
        }

        $headers = [
            'Tipo Documento', 'Numero Documento',
            'Primer Nombre', 'Segundo Nombre',
            'Primer Apellido', 'Segundo Apellido',
            'Sexo', 'Celular', 'Telefono 1', 'Telefono 2',
            'Correo Electronico', 'Tipo Afiliado', 'Regimen',
            'Categoria', 'IPS Primaria', 'Departamento', 'Municipio',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Consultas');

        // Write headers (row 1)
        foreach ($headers as $colIdx => $header) {
            $sheet->setCellValue([$colIdx + 1, 1], $header);
        }

        // Style headers
        $lastCol = chr(64 + count($headers)); // A=65, so 17 cols = Q
        $headerRange = "A1:{$lastCol}1";

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '1E40AF'],
                ],
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(28);

        // Write data rows
        $row = 2;
        foreach ($consultas as $c) {
            $data = [
                $c->tipo_documento,
                $c->numero_documento,
                $c->primer_nombre,
                $c->segundo_nombre,
                $c->primer_apellido,
                $c->segundo_apellido,
                $c->sexo,
                $c->celular,
                $c->telefono1,
                $c->telefono2,
                $c->correo_electronico,
                $c->tipo_afiliado,
                $c->regimen,
                $c->categoria,
                $c->ips_primaria,
                $c->departamento,
                $c->municipio,
            ];

            foreach ($data as $colIdx => $value) {
                $sheet->setCellValue([$colIdx + 1, $row], $value);
            }

            // Alternate row colors
            $bgColor = ($row % 2 === 0) ? 'EFF6FF' : 'FFFFFF';
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $bgColor],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D1D5DB'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            $row++;
        }

        // Auto-size columns
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Freeze header row
        $sheet->freezePane('A2');

        // Auto-filter
        $sheet->setAutoFilter("A1:{$lastCol}" . ($row - 1));

        $filename = "consultas_{$lote}.xlsx";

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
