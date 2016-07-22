<div class="row">
    <div class="col-md-12">
        <table class="table table-hover" id="link" width="100%" cellpadding="0" cellspacing="0"
               border="0">
            <thead>
            <tr>
                <?php
                foreach ($columns as $column) {
                    echo "<th>$column</th>";
                }
                ?>
            </tr>
            </thead>
            <tfoot>
            <tr>
                <?php
                foreach ($columns as $column) {
                    echo "<th>$column</th>";
                }
                ?>
            </tr>

            </tfoot>
        </table>

    </div>


    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.12/css/dataTables.bootstrap.min.css">
    <script src="https://cdn.datatables.net/1.10.12/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.12/js/dataTables.bootstrap.min.js"></script>

    <style type="text/css" title="currentStyle">
        #link_length {
            display: inline-block;
            padding: 0px 10px;
        }
    </style>

    <script type="text/javascript">
        $(document).ready(function () {

            var Table = $('#link');

            /* Init the table */
            var DataTableOptions = {
                dom: 'Blrtip',
                "processing": true,
                "serverSide": true,
                "ajax": {
                    url: window.location.href,
                    method: 'GET'
                },
                rowId: 'invite',
                ordering: true,
                order: [[0, 'desc']],
                columnDefs: [
                    {orderable: false, targets: '_all'}
                ],
                columns: [
                    {
                        data: 'invite',
                        orderable: true
                    },
                    {
                        data: 'date',
                        orderable: true
                    },
                    {
                        data: 'status',
                        render: function (data, type, full, meta) {
                            return data = (data == 0) ? 'Enabled' : 'Disabled';
                        }
                    }
                ]
            };

            Table.dataTable(DataTableOptions);


        });
    </script>
