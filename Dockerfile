# Use CentOS 6 as the base image
FROM centos:6

# Update the repository URLs to use the vault
RUN sed -i 's/mirrorlist=http/mirrorlist=https/' /etc/yum.repos.d/CentOS-Base.repo && \
    sed -i 's|#baseurl=http://mirror.centos.org/centos/$releasever/os/$basearch/|baseurl=http://vault.centos.org/6.10/os/$basearch/|g' /etc/yum.repos.d/CentOS-Base.repo && \
    sed -i 's|#baseurl=http://mirror.centos.org/centos/$releasever/updates/$basearch/|baseurl=http://vault.centos.org/6.10/updates/$basearch/|g' /etc/yum.repos.d/CentOS-Base.repo && \
    sed -i 's|#baseurl=http://mirror.centos.org/centos/$releasever/extras/$basearch/|baseurl=http://vault.centos.org/6.10/extras/$basearch/|g' /etc/yum.repos.d/CentOS-Base.repo && \
    sed -i 's|#baseurl=http://mirror.centos.org/centos/$releasever/centosplus/$basearch/|baseurl=http://vault.centos.org/6.10/centosplus/$basearch/|g' /etc/yum.repos.d/CentOS-Base.repo

# Install centos-release-scl to get the scl repo files
RUN yum install -y centos-release-scl && \
    sed -i 's|mirrorlist=https://mirrorlist.centos.org/?release=$releasever&repo=sclo-rh&arch=$basearch|baseurl=http://vault.centos.org/6.10/sclo/$basearch/sclo-rh|g' /etc/yum.repos.d/CentOS-SCLo-scl-rh.repo && \
    sed -i 's|mirrorlist=https://mirrorlist.centos.org/?release=$releasever&repo=sclo-sclo&arch=$basearch|baseurl=http://vault.centos.org/6.10/sclo/$basearch/sclo-sclo|g' /etc/yum.repos.d/CentOS-SCLo-scl.repo && \
    yum clean all

# Install development tools and other dependencies
RUN yum -y update && \
    yum groupinstall -y "Development Tools" && \
    yum install -y wget && \
    yum clean all

# Install Developer Toolset (GCC 7)
RUN yum install -y devtoolset-7-gcc devtoolset-7-gcc-c++ devtoolset-7-binutils && \
    yum clean all

# Download and install Node.js
RUN wget https://nodejs.org/dist/v20.13.1/node-v20.13.1-linux-x64.tar.xz && \
    tar -xJf node-v20.13.1-linux-x64.tar.xz -C /usr/local --strip-components=1 && \
    rm node-v20.13.1-linux-x64.tar.xz && \
    yum clean all

# Enable the Developer Toolset (GCC 7)
RUN echo "source /opt/rh/devtoolset-7/enable" >> /etc/profile

# Set Node.js environment variables
ENV PATH="/usr/local/bin:${PATH}"

# Install Nexe globally
RUN npm install -g nexe

# Set the working directory
WORKDIR /app

# Copy the application files
COPY . .

# Build the application using Nexe
CMD ["nexe", "thetvapp-win64.js", "-t", "linux-x64-20.13.1", "-o", "thetvapp-linux", "--build"]
