# abraflexi-export-tools

A set of tools for exporting data and documents from AbraFlexi (FlexiBee) accounting system, including invoices and their attachments.

## Features

- Export all received invoices (`faktura-prijata`) as PDF
- Download and process all attachments for each invoice
- Extract and merge PDFs from ISDOCX attachments
- Skip ISDOC (XML) attachments automatically
- Merge all PDFs (invoices and attachments) into a single PDF file
- Configurable via `.env` file or command-line options

## Requirements

- PHP 8.0+
- Composer dependencies (see `composer.json`)
- `pdfunite` utility (from poppler-utils) for PDF merging

## Usage

1. **Configure environment:**
   - Copy `example.env` to `.env` and fill in your AbraFlexi credentials and company code.

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Run the export script:**
   ```bash
   cd src
   php export_faktura_prijata_with_attachments.php
   ```
   - Use `-o <output.json>` to specify a report file.
   - Use `-e <envfile>` to specify a custom environment file.

4. **Result:**
   - The merged PDF will be saved as `all-invoices-and-attachments.pdf` in the project root.
   - A JSON report will be saved to the specified output or to stdout.

## How it works

- The script fetches all received invoices from AbraFlexi using the API.
- Each invoice is downloaded as a PDF.
- All attachments are downloaded. If an attachment is an ISDOCX file, the script extracts any PDF inside and includes it in the merge. ISDOC files are skipped.
- All PDFs are merged into a single file using `pdfunite`.

## License

See [LICENSE](LICENSE).

## Author

Vitex Software <info@vitexsoftware.cz>

## Thanks to Our Sponsor

Special thanks to our sponsor, [Utopia](https://utopia.cz/), for their support and contributions to this project.

![Project Logo](utopialibri.svg)
