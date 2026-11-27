import { Fragment } from 'react';
import { Dialog, Transition } from '@headlessui/react';

export default function Modal({ children, show = false, maxWidth = '2xl', closeable = true, onClose = () => {} }) {
    const close = () => {
        if (closeable) {
            onClose();
        }
    };

    const maxWidthClass = {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[maxWidth];

    return (
        <Transition show={show} as={Fragment} leave="duration-150">
            <Dialog
                as="div"
                id="modal"
                className="fixed inset-0 flex overflow-y-auto px-4 py-6 sm:px-0 items-center z-50"
                onClose={close}
            >
                <Transition.Child
                    as={Fragment}
                    enter="ease-out duration-200 motion-reduce:transition-none"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-150 motion-reduce:transition-none"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="absolute inset-0 bg-gray-500/75" />
                </Transition.Child>

                <Transition.Child
                    as={Fragment}
                    enter="ease-out duration-200 motion-reduce:transition-none"
                    enterFrom="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-[0.98]"
                    enterTo="opacity-100 translate-y-0 sm:scale-100"
                    leave="ease-in duration-150 motion-reduce:transition-none"
                    leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                    leaveTo="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-[0.98]"
                >
                    <Dialog.Panel
                        className={`mb-6 bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full sm:mx-auto ${maxWidthClass}`}
                    >
                        {children}
                    </Dialog.Panel>
                </Transition.Child>
            </Dialog>
        </Transition>
    );
}
