import template from './search-box.twig';
import './search-box.scss';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
  title: 'Components/Search Box',
  render: (args) => renderTwig(template, args),
};
export const Default = { 
  args: { 
    placeholder: 'Search Schools',
    action_url: '/search/node',
    input_name: 'keys'
  } 
};