<?php

return [
    /*
    | Từ ngày này: chỉ giao dịch thanh toán đúng kỳ (sau ngày đến hạn) mới đánh dấu đã trả và trừ gốc.
    */
    'principal_reduction_cutoff' => env('LOAN_PRINCIPAL_REDUCTION_CUTOFF', '2026-06-01'),
];
