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
      end

      private

      def expand_path
        File.dirname($PROGRAM_NAME) + '/' + @path
      end
    end
  end
end
