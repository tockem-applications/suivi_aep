function downloadCSV2(data, filename ) {
    console.log(5555);
    if (!data || !Array.isArray(data) || data.length === 0) {
        console.error('No data provided or data is not an array');
        return;
    }

    // Extract headers from the first object
    const headers = Object.keys(data[0]);

    // Function to escape CSV values (handle quotes, commas, etc.)
    const escapeCSV = (value) => {
        if (value === null || value === undefined) return '';
        const stringValue = value.toString();
        if (stringValue.includes(';') || stringValue.includes('"') || stringValue.includes('\n')) {
            return `"${stringValue.replace(/"/g, '""')}"`;
        }
        return stringValue;
    };

    // Format numbers to two decimal places for financial fields
    const formatValue = (value, key) => {
        if (typeof value === 'number' && 
            ['montant_conso', 'montant_conso_entretien', 'montant_conso_tva', 
             'montant_total', 'montant_restant', 'montant_verse', 
             'montant_a_valider', 'impaye', 'impayer_cumule', 'total_cumule'].includes(key)) {
            return value.toFixed(2);
        }
        return value;
    };

    // Create CSV content55.json
    let csvContent = headers.join(';') + '\n';
    data.forEach(row => {
        const rowValues = headers.map(header => 
            escapeCSV(formatValue(row[header], header))
        );
        csvContent += rowValues.join(';') + '\n';
    });

    // Create a Blob with CSV content55.json
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);

    // Set link attributes for download
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';

    // Append link to DOM, trigger click, and remove it
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}