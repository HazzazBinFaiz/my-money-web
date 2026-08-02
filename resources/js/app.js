import Alpine from 'alpinejs';
import bulkTransactions from './bulk-transactions';
import imagePicker from './image-picker';
import optionPicker from './option-picker';
import transactionForm from './transaction-form';

window.Alpine = Alpine;

Alpine.data('bulkTransactions', bulkTransactions);
Alpine.data('imagePicker', imagePicker);
Alpine.data('optionPicker', optionPicker);
Alpine.data('transactionForm', transactionForm);

Alpine.start();
