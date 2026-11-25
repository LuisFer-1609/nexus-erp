<?php

/*
|--------------------------------------------------------------------------
| Nexus IA: configuración del asistente y de la preparación de datos (RAG)
|--------------------------------------------------------------------------
|
| Todo lo que el asistente necesita para operar se controla desde aquí o desde
| el .env. No hay listas de tablas por módulo: la carga descubre el esquema de
| la base de datos por sí sola en cada ejecución.
|
*/

return [
    'assistant_name' => 'Nexus',

    /*
     * Proveedor de embeddings:
     *  - gemini: API de Google (requiere GEMINI_API_KEYS).
     *  - local:  vectores léxicos locales, sin red. Solo para desarrollo/pruebas.
     */
    'driver' => env('NEXUS_AI_DRIVER', 'gemini'),

    'gemini' => [
        // Pool de llaves: separadas por coma, espacio o salto de línea.
        'keys' => array_values(array_filter(array_map(
            'trim',
            preg_split('/[\s,]+/', (string) env('GEMINI_API_KEYS', '')) ?: [],
        ))),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'chat_model' => env('GEMINI_CHAT_MODEL', 'gemini-3.1-flash-lite'),
        'embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-001'),
        'dimensions' => (int) env('GEMINI_EMBEDDING_DIMENSIONS', 768),
        'timeout' => (int) env('GEMINI_TIMEOUT', 20),
        // Cuántas llaves distintas se intentan antes de rendirse.
        'max_attempts' => (int) env('GEMINI_MAX_ATTEMPTS', 4),
        // Segundos que una llave descansa tras un 429 / error de servidor.
        'cooldown_seconds' => (int) env('GEMINI_KEY_COOLDOWN', 60),
        // Segundos que se aparta una llave rechazada como inválida (401/403).
        'invalid_key_cooldown_seconds' => 3600,
        'temperature' => (float) env('GEMINI_TEMPERATURE', 0.1),
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 1500),
    ],

    // Puente de WhatsApp (whatsapp-bridge/): cada usuario vincula su WhatsApp con un QR.
    'whatsapp' => [
        'url' => env('WHATSAPP_BRIDGE_URL', 'http://127.0.0.1:3100'),
        'token' => env('WHATSAPP_BRIDGE_TOKEN', ''),
        'timeout' => 8,
    ],

    // Zona horaria con la que se imprime la fecha de «Generado» en los reportes PDF.
    'report_timezone' => env('NEXUS_REPORT_TIMEZONE', 'America/Mexico_City'),

    'retrieval' => [
        // Fragmentos máximos que se entregan al modelo.
        'top_k' => (int) env('NEXUS_AI_TOP_K', 6),
        // Similitud mínima (0-1). Por debajo, Nexus responde que no tiene información
        // y ni siquiera consulta al modelo. Calibrado con gemini-embedding-001 (768 dim):
        // una pregunta sin relación puntúa ≤ 0.53 y una relevante ≥ 0.70.
        'min_score' => (float) env('NEXUS_AI_MIN_SCORE', 0.6),
        'query_cache_seconds' => 600,
    ],

    'chunking' => [
        'max_chars' => 1200,
        'overlap_chars' => 120,
    ],

    'sync' => [
        // Cada cuántos minutos corre la recarga programada.
        'schedule_minutes' => (int) env('NEXUS_AI_SYNC_MINUTES', 5),
        // Textos por llamada de embeddings.
        'embedding_batch_size' => 40,
        // Filas leídas por página al recorrer una tabla.
        'row_page_size' => 500,
        // Tabla raíz de la empresa y columna que marca a qué empresa pertenece un dato.
        'tenant_table' => 'companies',
        'tenant_column' => 'company_id',
        // Saltos máximos por llaves foráneas para llegar a la empresa de una fila.
        'max_fk_depth' => 4,
        // Máximo de valores distintos para resumir una columna por categorías.
        'max_distinct_values' => 25,

        // Tablas que nunca se indexan (infraestructura de Laravel y del propio asistente).
        // Se admite * como comodín.
        'exclude_tables' => [
            'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
            'sessions', 'password_reset_tokens', 'passkeys', 'auth_users',
            'ai_*', 'nexus_conversations', 'nexus_conversation_messages', 'nexus_chat*',
            // Vínculos de WhatsApp: contienen números de teléfono personales.
            'whatsapp_links',
        ],

        // Columnas que jamás entran al asistente (contraseñas, tokens, datos personales sensibles).
        // Se compara contra el nombre de la columna; se admite * como comodín.
        'sensitive_columns' => [
            '*passwd*', '*password*', '*token*', '*secret*', '*remember*',
            '*two_factor*', '*api_key*', '*credential*', '*hash*',
            'ssn', 'curp',
        ],

        // Columnas de auditoría que no aportan significado al asistente.
        'skip_columns' => [
            'created_user_id', 'updated_user_id', 'deleted_user_id',
            'updated_at', 'deleted_at',
        ],

        // Columnas que sirven de "nombre" legible al resolver una llave foránea.
        // Si una tabla no tiene ninguna, se intenta con el nombre de la tabla a la
        // que apunta (p. ej. un empleado se muestra con el nombre de su usuario) y,
        // por último, con las columnas de respaldo.
        'label_columns' => [
            'name', 'full_name', 'title', 'nickname', 'legal_name', 'business_name',
            'nationality',
        ],
        'label_fallback_columns' => ['description', 'code'],
    ],
];
