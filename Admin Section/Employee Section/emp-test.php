<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AG Grid Example</title>

    <!-- ag-Grid styles -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-grid.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-theme-alpine.css">

    <!-- ag-Grid script -->
    <script src="https://cdn.jsdelivr.net/npm/ag-grid-community@23.1.0/dist/ag-grid-community.min.noStyle.js"></script>
</head>
<body>
    <div id="myGrid" class="ag-theme-alpine" style="height: 500px; width: 100%;"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const gridOptions = {
                columnDefs: [
                    { headerName: 'Make', field: 'make' },
                    { headerName: 'Model', field: 'model' },
                    { headerName: 'Price', field: 'price' }
                ],
                rowData: [
                    { make: 'Toyota', model: 'Celica', price: 35000 },
                    { make: 'Ford', model: 'Mondeo', price: 32000 },
                    { make: 'Porsche', model: 'Boxster', price: 72000 }
                ]
            };

            // Initialize the grid
            new agGrid.Grid(document.getElementById('myGrid'), gridOptions);
        });
    </script>
</body>
</html>
