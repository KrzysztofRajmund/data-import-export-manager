CREATE DATABASE IF NOT EXISTS data_import_export_manager
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_0900_ai_ci;

USE data_import_export_manager;

CREATE TABLE IF NOT EXISTS datasets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX uq_datasets_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS dataset_columns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dataset_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    label VARCHAR(255) NOT NULL,
    data_type VARCHAR(20) NOT NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    is_required BOOLEAN NOT NULL DEFAULT FALSE,
    is_searchable BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX uq_dataset_columns_name (
        dataset_id,
        name
    ),
    INDEX idx_dataset_columns_dataset_position (
        dataset_id,
        position
    ),

    CONSTRAINT fk_dataset_columns_dataset
        FOREIGN KEY (dataset_id)
        REFERENCES datasets(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_dataset_columns_data_type
        CHECK (
            data_type IN (
                'string',
                'integer',
                'decimal',
                'boolean',
                'date',
                'datetime'
            )
        )
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS dataset_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dataset_id BIGINT UNSIGNED NOT NULL,
    data JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_dataset_records_dataset (
        dataset_id
    ),
    INDEX idx_dataset_records_created (
        created_at
    ),

    CONSTRAINT fk_dataset_records_dataset
        FOREIGN KEY (dataset_id)
        REFERENCES datasets(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS import_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dataset_id BIGINT UNSIGNED NOT NULL,
    filename VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    total_rows INT UNSIGNED NOT NULL DEFAULT 0,
    imported_rows INT UNSIGNED NOT NULL DEFAULT 0,
    failed_rows INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,

    INDEX idx_import_jobs_dataset (
        dataset_id
    ),
    INDEX idx_import_jobs_status (
        status
    ),
    INDEX idx_import_jobs_created (
        created_at
    ),

    CONSTRAINT fk_import_jobs_dataset
        FOREIGN KEY (dataset_id)
        REFERENCES datasets(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_import_jobs_status
        CHECK (
            status IN (
                'pending',
                'processing',
                'completed',
                'failed'
            )
        )
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS import_errors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    import_job_id BIGINT UNSIGNED NOT NULL,
    `row_number` INT UNSIGNED NOT NULL,
    column_name VARCHAR(255) NULL,
    error_message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_import_errors_job (
        import_job_id
    ),
    INDEX idx_import_errors_row (
        `row_number`
    ),

    CONSTRAINT fk_import_errors_job
        FOREIGN KEY (import_job_id)
        REFERENCES import_jobs(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS export_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dataset_id BIGINT UNSIGNED NOT NULL,
    format VARCHAR(10) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    total_rows INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,

    INDEX idx_export_jobs_dataset (
        dataset_id
    ),
    INDEX idx_export_jobs_status (
        status
    ),
    INDEX idx_export_jobs_created (
        created_at
    ),

    CONSTRAINT fk_export_jobs_dataset
        FOREIGN KEY (dataset_id)
        REFERENCES datasets(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_export_jobs_format
        CHECK (
            format IN (
                'xlsx',
                'csv',
                'pdf'
            )
        ),

    CONSTRAINT chk_export_jobs_status
        CHECK (
            status IN (
                'pending',
                'processing',
                'completed',
                'failed'
            )
        )
) ENGINE=InnoDB;