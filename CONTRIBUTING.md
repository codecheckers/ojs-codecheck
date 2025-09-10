# Contributing to the ojs-codecheck plugin

First off, thanks for taking the time to contribute! 

All types of contributions are encouraged and valued. See the [Table of Contents](#table-of-contents) for different ways to help and details about how this project handles them. Please make sure to read the relevant section before making your contribution. It will make it a lot easier for us maintainers and smooth out the experience for all involved. The community looks forward to your contributions. 

> And if you like ojs-codecheck, but just don't have time to contribute, that's fine. There are other easy ways to support ojs-codecheck, as well as the CHECK-PUB project and the CODECHECK initiative and show your appreciation, which we would also be very happy about:
> - Conduct a CODECHECK yourself
> - Star the project
> - Tweet about it
> - Refer this project in your project's README
> - Mention the project at local meetups and tell your friends/colleagues


## Table of Contents

- [I Have a Question](#i-have-a-question)
- [I Want To Contribute](#i-want-to-contribute)
- [Reporting Bugs](#reporting-bugs)
- [Suggesting Enhancements](#suggesting-enhancements)
- [Contributing to ojs-codecheck](#contributing-to-ojs-codecheck)
- [Coding Standards](#coding-standards)
- [Improving The Documentation](#improving-the-documentation)
- [Styleguides](#styleguides)
- [Commit Messages](#commit-messages)


## I Have a Question

> If you want to ask a question, we assume that you have read the available Documentation inside the [README.md](https://github.com/codecheckers/ojs-codecheck/blob/main/README.md),on the [CODECHECK](https://codecheck.org.uk/) and [CHECK-PUB](https://codecheck.org.uk/pub/) websites, as well as the [CODECHECK paper](https://doi.org/10.12688/f1000research.51738.2).

Before you ask a question, it is best to search for existing [Issues](/issues) that might help you. In case you have found a suitable issue and still need clarification, you can write your question as a comment to this issue. It is also advisable to search the internet for answers first.

If you then still feel the need to ask a question and need clarification, we recommend the following:

- Open an [Issue](/issues/new).
- Provide as much context as you can about what you're running into.
- Provide project and platform versions (OJS, PHP, etc), depending on what seems relevant.

We will then take care of the issue as soon as possible and as good as we can.



## I Want To Contribute

> ### Legal Notice 
> When contributing to this project, you must agree that you have authored 100% of the content, that you have the necessary rights to the content and that the content you contribute may be provided under the project [license](https://github.com/codecheckers/ojs-codecheck/blob/main/LICENSE).

### Reporting Bugs


#### Before Submitting a Bug Report

Your bug report shouldn't leave others needing to chase you up for more information. Therefore, we ask you kindly to investigate carefully, collect information and describe the issue in detail in your report. Please complete the following steps in advance to help us fix any potential bug as fast as possible.

- Make sure that you are using the latest version.
- Determine if your bug is really a bug and not an error on your side e.g. using incompatible OJS and/ or PHP versions (Make sure that you have read the [README.md](https://github.com/codecheckers/ojs-codecheck/blob/main/README.md). If you are looking for support, you might want to check [this section](#i-have-a-question)).
- To see if other users have experienced (and potentially already solved) the same issue you are having, check if there is not already a bug report existing for your bug or error in the [bug tracker](issues?q=label%3Abug).
- Also make sure to search the internet (including Stack Overflow) to see if users outside of the GitHub community have discussed the issue.
- Collect information about the bug:
  - Stack trace (Traceback)
  - OS, Platform and Version (Windows, Linux, macOS, x86, ARM)
  - OJS and PHP versions.
  - Possibly your input and the output (if the bug happened e.g. during CODECHECK metadata creation)
  - Can you reliably reproduce the issue, or was it just a one time occurenece?
  - Can you reproduce the bug with older versions as well?


#### How Do I Submit a Good Bug Report?

> You must never report security related issues, vulnerabilities or bugs including sensitive information to the issue tracker, or elsewhere in public. Instead sensitive bugs must be sent by email to <daniel.nuest@tu-dresden.de>.


We use GitHub issues to track bugs and errors. If you run into an issue with the project:

- Open an [Issue](/issues/new). (Since we can't be sure at this point whether it is a bug or not, we ask you not to talk about a bug yet and not to label the issue.)
- Explain the behavior you would expect and the actual behavior.
- Please provide as much context as possible and describe the *reproduction steps* that someone else can follow to recreate the issue on their own. This usually includes your code. For good bug reports you should isolate the problem and create a reduced test case.
- Provide the information you collected in the previous section.

Once it's filed:

- The project team will label the issue accordingly.
- A team member will try to reproduce the issue with your provided steps. If there are no reproduction steps or no obvious way to reproduce the issue, the team will ask you for those steps and mark the issue as `question`. Bugs with the `question` label will not be addressed until they are reproduced.
- If the team is able to reproduce the issue, it will be marked `bug`, as well as possibly other tags (such as `help wanted` or even `enhancement`), and the issue will be left to be [implemented by someone](#contributing-to-ojs-codecheck).




### Suggesting Enhancements

This section guides you through submitting an enhancement suggestion for the [ojs-codecheck plugin](https://github.com/codecheckers/ojs-codecheck/tree/main), **including completely new features and minor improvements to existing functionality**. Following these guidelines will help maintainers and the community to understand your suggestion and find related suggestions.


#### Before Submitting an Enhancement

- Make sure that you are using the latest version of the ojs-plugin as well as the recommended versions for OJS and PHP - see [README.md](https://github.com/codecheckers/ojs-codecheck/blob/main/README.md).
- Read the [README.md](https://github.com/codecheckers/ojs-codecheck/blob/main/README.md) carefully and find out if the functionality is already covered, maybe by an individual configuration.
- Perform a [search](/issues) to see if the enhancement has already been suggested. If it has, add a comment to the existing issue instead of opening a new one.
- Find out whether your idea fits with the scope and aims of the project. It's up to you to make a strong case to convince us of the merits of this feature. Keep in mind that we want features that will be useful to the majority of our users and not just a small subset, as well as features that follow the [CHECK-PUB project proposal](https://codecheck.org.uk/pub/) and the [CODECHECK community workflow](https://codecheck.org.uk/guide/community-workflow-overview). If you're just targeting a minority of users, consider marking the issue as such, so we can discuss further, if it is necessary.


#### How Do I Submit a Good Enhancement Suggestion?

Enhancement suggestions are tracked as [GitHub issues](/issues).

- Please **mark your enhancement issue** as `enhancement`.
- Use a **clear and descriptive title** for the issue to identify the suggestion.
- Provide a **step-by-step description of the suggested enhancement** in as many details as possible (we would appreciate it if you use checkboxes to list possible individual development steps).
- **Describe the current behavior** and **explain which behavior you expected to see instead** and why. At this point you can also tell which alternatives do not work for you.
- You may want to **include screenshots and animated GIFs of Mock-Ups** which help you demonstrate the steps or point out the part which the suggestion is related to.
  - You can use [this tool](https://www.cockos.com/licecap/) to record GIFs on macOS and Windows, and [this tool](https://github.com/colinkeenan/silentcast) or [this tool](https://github.com/GNOME/byzanz) on Linux.
  - You can use [Figma](https://www.figma.com/) to create Mock-Ups or directly change the OJS UI in your browser if it is a smaller mock-up.
- **Explain why this enhancement would be useful** to most users of the ojs-codecheck plugin. You may also want to point out the other projects that solved it better and which could serve as inspiration.



### Contributing to ojs-codecheckk

If you want to contribute to this project, please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes following the [coding standards](#coding-standards)
4. Update tests and documentation
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

### Coding Standards

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards
- Use meaningful variable and function names
- Document all public methods and classes
- Maintain backward compatibility within major versions

### Improving The Documentation

When contributing to the documentation inside the [README.md](https://github.com/codecheckers/ojs-codecheck/blob/main/README.md), [CONTRIBUTING.md](https://github.com/codecheckers/ojs-codecheck/blob/main/CONTRIBUTING.md), or [CHANGELOG.md]() please follow the same rules listed in [Contributing to ojs-codecheckk](#contributing-to-ojs-codecheckk). Instead of creating a feature branch though, please consider creating a documentation branch (`git checkout -b documentation/my-doc-improvement`).

## Styleguides
### Commit Messages

Please try to keep your commit messages short and concise and mark the issue you are working on in your commit message.

## Attribution
This guide is based on the **contributing.md**. [Make your own](https://contributing.md/)!
