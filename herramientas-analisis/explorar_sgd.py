r"""Inventario e inspección estática del SGD; no inicia el aplicativo ni conecta a BD.

Uso: python herramientas-analisis/explorar_sgd.py --origen D:\APP-SGD\sgd
Requiere javap en PATH. Solo escribe en el repositorio del reporteador.
"""
import argparse
import collections
import csv
import hashlib
import json
from pathlib import Path
import re
import subprocess
from datetime import datetime, timezone
import zipfile


def sha256(path):
    digest = hashlib.sha256()
    with path.open('rb') as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b''):
            digest.update(chunk)
    return digest.hexdigest()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--origen', type=Path, required=True)
    args = parser.parse_args()
    source = args.origen.resolve(strict=True)
    repo = Path(__file__).resolve().parents[1]
    if source == repo or source in repo.parents or repo in source.parents:
        raise ValueError('Origen y repositorio deben estar separados.')
    output = repo / 'docs/fase-1/evidencia'
    scratch = repo / '.analisis-sgd'
    sql = repo / 'docs/fase-1/sql'
    for path in (output, scratch, sql):
        path.mkdir(parents=True, exist_ok=True)
    files = sorted(p for p in source.rglob('*') if p.is_file())
    before = {p.relative_to(source).as_posix(): sha256(p) for p in files}
    with (output / 'inventario.csv').open('w', encoding='utf-8', newline='') as stream:
        writer = csv.writer(stream)
        writer.writerow(['ruta_relativa', 'bytes', 'modificado_utc', 'sha256'])
        for path in files:
            stat = path.stat()
            writer.writerow([path.relative_to(source).as_posix(), stat.st_size,
                             datetime.fromtimestamp(stat.st_mtime, timezone.utc).isoformat(),
                             before[path.relative_to(source).as_posix()]])
    classes = {
        'BandejaEntradaController': 'pe.gob.onpe.tramitedoc.web.controller',
        'AvisoBandejaEntradaServiceImp': 'pe.gob.onpe.tramitedoc.service.impl',
        'AvisoBandejaEntradaDaoImp': 'pe.gob.onpe.tramiteconv.dao.impl.sqlserver',
        'RecepDocumentoAdmDaoImp': 'pe.gob.onpe.tramiteconv.dao.impl.sqlserver',
        'DocumentoAdmRecepcionController': 'pe.gob.onpe.tramitedoc.web.controller',
        'RecepDocumentoAdmServiceImp': 'pe.gob.onpe.tramitedoc.service.impl',
    }
    jar = source / 'WEB-INF/lib/tramitedoc-logic-1.0.jar'
    dumps = {}
    for name, package in classes.items():
        result = subprocess.run(['javap', '-J-Dfile.encoding=UTF-8', '-classpath', str(jar),
                                 '-p', '-c', '-v', package + '.' + name],
                                capture_output=True, check=True)
        dumps[name] = result.stdout.decode('utf-8')
        (scratch / (name + '.javap.txt')).write_text(dumps[name], encoding='utf-8')
    # Estas cuatro constantes son consultas completas, no fragmentos dinámicos.
    for number, name in [(13, '01-entrada-total-original'), (15, '02-delegados-total-original'),
                         (43, '03-no-leidos-personal-original'), (42, '04-recibidos-personal-original')]:
        match = re.search(r'^\s*#' + str(number) + r' = String\s+#[0-9]+\s+// (.*)$',
                          dumps['AvisoBandejaEntradaDaoImp'], re.MULTILINE)
        if not match:
            raise ValueError(f'Constante #{number} no encontrada; revisar versión del JAR.')
        query = match.group(1).replace('\\n', '\n').replace('\\t', '\t')
        if not query.lstrip().upper().startswith('SELECT'):
            raise ValueError('La constante ya no corresponde a un SELECT.')
        header = ('-- Evidencia estática: AvisoBandejaEntradaDaoImp, constante #' + str(number)
                  + '.\n-- No ejecutada. Parámetros nombrados de Spring (:pCoDep, :pCoEmp).\n'
                  '-- Preserva NOLOCK y las reglas originales exclusivamente para contraste.\n')
        (sql / (name + '.sql')).write_text(header + query + ';\n', encoding='utf-8')
    with zipfile.ZipFile(jar) as archive:
        class_hashes = {package + '.' + name: hashlib.sha256(
            archive.read(package.replace('.', '/') + '/' + name + '.class')).hexdigest()
            for name, package in classes.items()}
    after_files = sorted(p for p in source.rglob('*') if p.is_file())
    after = {p.relative_to(source).as_posix(): sha256(p) for p in after_files}
    summary = {
        'origen': str(source), 'capturado_utc': datetime.now(timezone.utc).isoformat(),
        'archivos': len(files), 'extensiones': dict(collections.Counter(p.suffix.lower() for p in files)),
        'bytes_totales': sum(p.stat().st_size for p in files), 'sha256_clases': class_hashes,
        'origen_sin_cambios': before == after,
        'herramienta': subprocess.run(['javap', '-version'], capture_output=True, text=True).stdout.strip(),
        'base_de_datos_consultada': False, 'sgd_ejecutado': False,
    }
    (output / 'resumen-inventario.json').write_text(
        json.dumps(summary, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(json.dumps(summary, ensure_ascii=False, indent=2))
    if before != after:
        raise RuntimeError('El contenido de origen cambió durante la inspección.')


if __name__ == '__main__':
    main()
