@use('App\Domain\Finance\InvoiceStatus')
<span @class([
    'rounded px-2 py-0.5 text-xs font-medium',
    'bg-emerald-50 text-emerald-800' => $status === InvoiceStatus::Paid,
    'bg-amber-50 text-amber-800' => $status === InvoiceStatus::PartiallyPaid,
    'bg-red-50 text-red-800' => $status === InvoiceStatus::Issued,
    'bg-gray-100 text-gray-600 line-through' => $status === InvoiceStatus::Voided,
])>{{ $status->label() }}</span>
