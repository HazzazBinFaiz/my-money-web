import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import bookImport from './book-import';
import bulkTransactions from './bulk-transactions';
import imagePicker from './image-picker';
import optionPicker from './option-picker';
import reportDetail from './report-detail';
import transactionForm from './transaction-form';

window.Alpine = Alpine;

Alpine.plugin(collapse);

Alpine.data('bookImport', bookImport);
Alpine.data('bulkTransactions', bulkTransactions);
Alpine.data('imagePicker', imagePicker);
Alpine.data('optionPicker', optionPicker);
Alpine.data('reportDetail', reportDetail);
Alpine.data('transactionForm', transactionForm);

Alpine.start();
