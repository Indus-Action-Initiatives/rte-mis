import './accordion.scss';
import template from './accordion.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
    title: 'Components/Accordion',
    tags: ['autodocs'],
    render: (args) => renderTwig(template, args),
    argTypes: {
        question: { control: 'text', description: 'Trigger text' },
        answer: { control: 'text', description: 'Collapsed content' },
        open: { control: 'boolean', description: 'Initially expanded' },
    },
};

export const Closed = {
    args: {
        question: 'How should I apply for student registration?',
        answer: 'You can apply online through the official RTE portal during the admission period. Visit the official RTE website, click "Student Registration", login with your parent mobile number, fill in student details, and submit the form.',
        open: false,
    },
};

export const Open = {
    args: {
        question: 'Do we need to pay for the application?',
        answer: 'NO. The RTE application process is completely free of cost. There is no registration fee or admission charge under the RTE quota.',
        open: true,
    },
};

export const LongAnswer = {
    args: {
        question: 'What documents are required for registration?',
        answer: "Commonly required documents include: Student's birth certificate (age proof), Residence proof (Aadhaar card / electricity bill / ration card), Parent/guardian identity proof, Income certificate (if applicable), Caste certificate (if applicable), Passport-size photograph. All documents must be clear, valid, and in PDF/JPG format (max 2MB each).",
        open: false,
    },
};
