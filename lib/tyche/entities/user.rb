module Tyche
  module Entities
    class User < ActiveRecord::Base
      self.table_name = 'User'
      self.inheritance_column = 'unknown'
    end
  end
end
