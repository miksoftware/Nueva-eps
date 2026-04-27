# API Nueva EPS — Consulta de Afiliado por Cédula

## Descripción

Retorna el **historial completo** de consultas realizadas a Nueva EPS para un número de cédula específico, ordenado del registro **más reciente al más antiguo**. Solo se incluyen consultas con estado `completado`.

---

## Endpoint

```
GET /api/consulta/cedula/{cedula}
```

### Parámetros de ruta

| Parámetro | Tipo   | Requerido | Descripción                     |
|-----------|--------|-----------|---------------------------------|
| `cedula`  | string | Sí        | Número de cédula (solo dígitos) |

### Autenticación

Requiere token **Bearer** de Sanctum en el header de la petición.

```
Authorization: Bearer <token>
```

---

## Ejemplo de petición

```http
GET /api/consulta/cedula/1234567890
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Accept: application/json
```

---

## Respuestas

### 200 — Consulta exitosa

```json
{
  "success": true,
  "message": "Consulta exitosa.",
  "total": 2,
  "data": [
    {
      "cedula": "1234567890",
      "tipo_documento": "CC",
      "primer_nombre": "CARLOS",
      "segundo_nombre": "ANDRES",
      "primer_apellido": "RAMÍREZ",
      "segundo_apellido": "LÓPEZ",
      "sexo": "M",
      "celular": "3201234567",
      "telefono1": "6012345678",
      "telefono2": null,
      "correo_electronico": "carlos.ramirez@correo.com",
      "tipo_afiliado": "COTIZANTE",
      "regimen": "CONTRIBUTIVO",
      "categoria": "A",
      "ips_primaria": "CLÍNICA DEL COUNTRY",
      "departamento": "CUNDINAMARCA",
      "municipio": "BOGOTÁ D.C.",
      "consultado_en": "2026-04-27T10:30:00+00:00"
    },
    {
      "cedula": "1234567890",
      "tipo_documento": "CC",
      "primer_nombre": "CARLOS",
      "segundo_nombre": "ANDRES",
      "primer_apellido": "RAMÍREZ",
      "segundo_apellido": "LÓPEZ",
      "sexo": "M",
      "celular": "3201234567",
      "telefono1": null,
      "telefono2": null,
      "correo_electronico": null,
      "tipo_afiliado": "COTIZANTE",
      "regimen": "CONTRIBUTIVO",
      "categoria": "A",
      "ips_primaria": "CLÍNICA DEL COUNTRY",
      "departamento": "CUNDINAMARCA",
      "municipio": "BOGOTÁ D.C.",
      "consultado_en": "2026-03-15T08:00:00+00:00"
    }
  ]
}
```

### 404 — Sin resultados

```json
{
  "success": false,
  "message": "No se encontraron resultados para la cédula proporcionada.",
  "data": null
}
```

---

## Descripción de campos del JSON de respuesta

### Nivel raíz

| Campo     | Tipo    | Descripción                                                    |
|-----------|---------|----------------------------------------------------------------|
| `success` | boolean | `true` si la operación fue exitosa, `false` en caso contrario |
| `message` | string  | Mensaje descriptivo del resultado                              |
| `total`   | integer | Cantidad total de registros retornados                         |
| `data`    | array   | Arreglo de objetos con el historial de consultas               |

### Objeto dentro de `data[]`

| Campo                | Tipo            | Descripción                                                                    |
|----------------------|-----------------|--------------------------------------------------------------------------------|
| `cedula`             | string          | Número de documento del afiliado (`numero_documento` en BD)                    |
| `tipo_documento`     | string / null   | Tipo de documento (ej. `CC`, `TI`, `CE`, `PA`)                                |
| `primer_nombre`      | string / null   | Primer nombre del afiliado                                                     |
| `segundo_nombre`     | string / null   | Segundo nombre del afiliado. Puede ser `null`                                  |
| `primer_apellido`    | string / null   | Primer apellido del afiliado                                                   |
| `segundo_apellido`   | string / null   | Segundo apellido del afiliado. Puede ser `null`                                |
| `sexo`               | string / null   | Sexo del afiliado (`M` = Masculino, `F` = Femenino)                            |
| `celular`            | string / null   | Número de celular de contacto registrado                                       |
| `telefono1`          | string / null   | Primer teléfono de contacto adicional. Puede ser `null`                        |
| `telefono2`          | string / null   | Segundo teléfono de contacto adicional. Puede ser `null`                       |
| `correo_electronico` | string / null   | Correo electrónico de contacto. Puede ser `null`                               |
| `tipo_afiliado`      | string / null   | Tipo de afiliado (ej. `COTIZANTE`, `BENEFICIARIO`)                             |
| `regimen`            | string / null   | Régimen de salud (ej. `CONTRIBUTIVO`, `SUBSIDIADO`)                            |
| `categoria`          | string / null   | Categoría del afiliado asignada por Nueva EPS (ej. `A`, `B`, `C`)             |
| `ips_primaria`       | string / null   | IPS primaria asignada al afiliado                                              |
| `departamento`       | string / null   | Departamento de residencia registrado                                          |
| `municipio`          | string / null   | Municipio de residencia registrado                                             |
| `consultado_en`      | string ISO 8601 | Fecha y hora en que se realizó la consulta (UTC)                               |

---

## Notas

- Los registros se ordenan de **más reciente a más antiguo** según el campo `consultado_en`.
- Solo se retornan consultas con `estado = 'completado'`.
- Si la cédula no tiene registros en la base de datos, se retorna HTTP `404`.
- El campo `cedula` en la URL solo acepta dígitos numéricos; cualquier otro carácter retorna `404` automáticamente.
