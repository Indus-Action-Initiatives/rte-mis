import './application-process.scss';
import template from './application-process.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/Application Process',
  tags: ['autodocs'],
  render: (args) => renderTwig(template, args),
  argTypes: {},
};

export const Default = {
  args: {
    title: 'Application Process',
    subtitle: 'Complete your RTE admission in 6 simple steps',
    steps: [
      {
        icon: 'register',
        title: 'Register',
        description: 'Create account with mobile number'
      },
      {
        icon: 'document',
        title: 'Fill Details',
        description: 'Enter student information'
      },
      {
        icon: 'upload',
        title: 'Upload Documents',
        description: 'Submit required documents'
      },
      {
        icon: 'school',
        title: 'Select Schools',
        description: 'Choose up to 5 schools'
      },
      {
        icon: 'submit',
        title: 'Submit',
        description: 'Review and submit application'
      },
      {
        icon: 'track',
        title: 'Track Status',
        description: 'Monitor application progress'
      }
    ],
    guidelines: {
      title: 'Important Guidelines',
      items: [
        'Keep documents in PDF/JPG format (max 2MB each)',
        'Applications can be saved as draft and completed later',
        'SMS and email notifications at each stage',
        'Transparent lottery system for fair seat allocation'
      ],
      button: {
        text: 'Apply Now',
        url: '#'
      }
    }
  },
};
