# Gemini API Provider for Moodle

## Overview

The Gemini API Provider plugin integrates Google's Gemini AI models into Moodle's AI framework, enabling powerful AI capabilities across your learning management system. This plugin supports text generation, image generation, and text summarization using Google's state-of-the-art AI models.

## Features

### Supported AI Actions

- **Text Generation**: Generate creative and informative text responses using Gemini models
- **Image Generation**: Create images from text prompts using Google's Imagen models
- **Text Summarization**: Automatically summarize long text content for better comprehension

### Key Features

- **Multiple Model Support**: Choose from various Gemini models (Pro, Flash, Flash-Lite) and Imagen models
- **Rate Limiting**: Configurable global and per-user rate limiting to manage API usage
- **Privacy-First**: No personal data is stored locally; user identification is anonymized
- **Flexible Configuration**: Customizable system instructions and API endpoints
- **Moodle Integration**: Seamlessly integrates with Moodle's core AI framework

## Installation

### Prerequisites

- Moodle 4.1+ (requires 2024100100)
- PHP 8.0 or higher
- Valid Google AI Studio API key

### Installation Steps

1. **Download the Plugin**
   - Download the plugin from the [Moodle plugins directory](https://moodle.org/plugins/view/aiprovider_gemini)
   - Or clone from the GitHub repository

2. **Install the Plugin**
   - Extract the plugin to your Moodle installation's `/ai/provider/` directory
   - The final path should be: `/ai/provider/aiprovider_gemini/`

3. **Install via Moodle Admin**
   - Log in to your Moodle site as an administrator
   - Navigate to **Site administration > Notifications**
   - Follow the installation prompts

4. **Configure the Plugin**
   - Go to **Site administration > Plugins > AI providers > Gemini API provider**
   - Enter your Google AI Studio API key
   - Configure rate limiting settings as needed

## Configuration

### API Key Setup

1. **Get a Google AI Studio API Key**
   - Visit [Google AI Studio](https://aistudio.google.com/apikey)
   - Create a new API key
   - Copy the key to your clipboard

2. **Configure in Moodle**
   - Navigate to **Site administration > Plugins > AI providers > Gemini API provider**
   - Paste your API key in the "Gemini API key" field
   - Save changes

### Rate Limiting Configuration

The plugin supports two types of rate limiting:

#### Global Rate Limiting
- **Enable**: Check "Set site-wide rate limit"
- **Limit**: Set maximum requests per hour for the entire site
- **Default**: 100 requests/hour

#### User Rate Limiting
- **Enable**: Check "Set user rate limit"
- **Limit**: Set maximum requests per hour per user
- **Default**: 10 requests/hour

### Model Configuration

#### Text Generation Models
- **Default**: `gemini-2.5-flash`
- **Available**: All Gemini models (Pro, Flash, Flash-Lite variants)
- **System Instructions**: Customizable prompts for consistent AI behavior

#### Image Generation Models
- **Default**: `imagen-3.0-generate-002`
- **Available**: All Imagen models
- **Features**: High-quality image generation from text prompts

#### Text Summarization Models
- **Default**: `gemini-2.5-flash`
- **Features**: Intelligent text summarization with configurable instructions

## Usage

### For Administrators

1. **Enable AI Features**
   - Navigate to **Site administration > Plugins > AI providers**
   - Enable the Gemini API provider
   - Configure global settings

2. **Monitor Usage**
   - Check rate limiting status in the admin interface
   - Monitor API usage through Google AI Studio dashboard

### For Teachers

1. **Access AI Features**
   - AI features are available in supported activities and resources
   - Look for AI-powered options in content creation tools

## Troubleshooting

### Common Issues

#### API Key Errors
- **Problem**: "You need to insert API key before"
- **Solution**: Verify your API key is correctly entered in the plugin settings

#### Rate Limiting Issues
- **Problem**: "Rate limit exceeded" errors
- **Solution**: Check your rate limiting settings and increase limits if necessary

#### Model Loading Issues
- **Problem**: Models not appearing in dropdown
- **Solution**: Verify your API key has access to the required models

### Debug Information

Enable debugging in Moodle to get detailed error information:
1. Go to **Site administration > Development > Debugging**
2. Enable debugging and set appropriate levels
3. Check the debug output for detailed error messages

## Support

### Getting Help

- **Documentation**: This README and inline help in Moodle
- **Issue Tracker**: [GitHub Issues](https://github.com/your-repo/aiprovider_gemini/issues)

### Contributing

We welcome contributions! Please:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## Version History

### Version 2025082700 (Current)
- Initial stable release
- Support for text generation, image generation, and text summarization
- Rate limiting capabilities
- Multiple model support

## License

This plugin is licensed under the [GNU General Public License v3.0](https://www.gnu.org/licenses/gpl-3.0.html).

## Credits

- **Developer**: Andrea Bertelli (andrea.bertelli@unife.it)
- **Institution**: University of Ferrara, Italy
- **Copyright**: 2025 University of Ferrara, Italy

## See Also

- [Google AI Studio Documentation](https://ai.google.dev/docs)
- [Moodle Plugin Development Guide](https://docs.moodle.org/dev/Plugin_types) 
