import { parentPort } from 'node:worker_threads';

function isEmpty(value) {
  return value === null || value === undefined || (typeof value === 'string' && value.trim() === '');
}

function validateValue(column, value) {
  if (isEmpty(value)) {
    if (column.isRequired) throw new Error(`Field "${column.label}" is required.`);
    return null;
  }

  const text = typeof value === 'string' ? value.trim() : value;
  switch (column.dataType) {
    case 'string': {
      const result = String(text);
      if (result.length > 255) throw new Error(`Field "${column.label}" must be at most 255 characters.`);
      return result;
    }
    case 'integer': {
      if ((typeof text !== 'number' && !/^[-+]?\d+$/.test(String(text))) || !Number.isInteger(Number(text))) {
        throw new Error(`Field "${column.label}" must be a valid integer.`);
      }
      return Number(text);
    }
    case 'decimal': {
      if (typeof text !== 'number' && (typeof text !== 'string' || text.trim() === '' || !Number.isFinite(Number(text)))) {
        throw new Error(`Field "${column.label}" must be a valid decimal number.`);
      }
      return Number(text);
    }
    case 'boolean': {
      if (typeof text === 'boolean') return text;
      const normalized = String(text).toLowerCase();
      if (['1', 'true', 'yes', 'on'].includes(normalized)) return true;
      if (['0', 'false', 'no', 'off'].includes(normalized)) return false;
      throw new Error(`Field "${column.label}" must be true or false.`);
    }
    case 'date': {
      const match = /^\d{4}-\d{2}-\d{2}$/.exec(String(text));
      if (!match) throw new Error(`Field "${column.label}" must use the YYYY-MM-DD date format.`);
      const date = new Date(`${text}T00:00:00Z`);
      if (Number.isNaN(date.getTime()) || date.toISOString().slice(0, 10) !== String(text)) {
        throw new Error(`Field "${column.label}" must use the YYYY-MM-DD date format.`);
      }
      return String(text);
    }
    case 'datetime': {
      const match = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.exec(String(text));
      if (!match) throw new Error(`Field "${column.label}" must use the YYYY-MM-DDTHH:MM datetime format.`);
      const normalized = `${text}:00`;
      const date = new Date(normalized);
      if (Number.isNaN(date.getTime())) throw new Error(`Field "${column.label}" must use the YYYY-MM-DDTHH:MM datetime format.`);
      return normalized;
    }
    default:
      throw new Error('Unsupported column data type.');
  }
}

parentPort.on('message', (payload) => {
  const { headers, mapping, rows, headerRow, columns, batchOffset } = payload;
  const definitions = new Map(columns.map((column) => [column.name, column]));
  const validRows = [];
  const errors = [];
  const seen = new Set();

  for (let rowIndex = 0; rowIndex < rows.length; rowIndex += 1) {
    const row = rows[rowIndex];
    const rowNumber = headerRow + (batchOffset + rowIndex) + 1;
    const input = {};
    const rowErrors = [];

    for (let index = 0; index < headers.length; index += 1) {
      const setting = mapping[String(index)] || mapping[index] || {};
      if (setting.skip) continue;

      const name = setting.name;
      const column = name ? definitions.get(name) : null;
      if (!column) {
        rowErrors.push({ rowNumber, columnName: name || String(headers[index]), message: `Field "${setting.label || headers[index]}" is not a valid dataset column.` });
        continue;
      }

      try {
        input[name] = validateValue(column, row[index]);
      } catch (error) {
        rowErrors.push({ rowNumber, columnName: name, message: error.message });
      }
    }

    for (const column of columns) {
      if (!Object.prototype.hasOwnProperty.call(input, column.name) && column.isRequired) {
        rowErrors.push({ rowNumber, columnName: column.name, message: `Field "${column.label}" is required.` });
      } else if (!Object.prototype.hasOwnProperty.call(input, column.name)) {
        input[column.name] = null;
      }
    }

    const canonical = JSON.stringify(input);
    if (seen.has(canonical)) {
      rowErrors.push({ rowNumber, columnName: null, message: 'Duplicate record: this row repeats a record in the import file.' });
    } else if (rowErrors.length === 0) {
      seen.add(canonical);
      validRows.push({ rowNumber, data: input });
    }

    if (rowErrors.length > 0) errors.push(...rowErrors);
  }

  parentPort.postMessage({ validRows, errors });
});
