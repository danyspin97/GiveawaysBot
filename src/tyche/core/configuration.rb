module Tyche
  module Core
    ##
    # Gets user's options from a configuration file.
    class Configuration
      attr_reader :options

      def initialize(path, loader = YAML)
        @path = path
        @options = {}
        @loader = loader
      end

      def load
        @options = @loader.load_file(expand_path)
      rescue Errno::ENOENT
        $stderr.puts "Unable to find '#{expand_path}': missing configuration file"
        exit(1)
      end

      private

      def expand_path
        File.dirname($PROGRAM_NAME) + '/' + @path
      end
    end
  end
end
